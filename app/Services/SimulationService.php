<?php

namespace App\Services;

use App\DataObjects\SimulationResult;
use App\Enums\AuditCategory;
use App\Enums\Band;
use App\Enums\Domain;
use App\Enums\MaturityLevel;
use App\Enums\RiskCategory;
use App\Enums\Severity;
use App\Models\Assessment;
use App\Models\AssessmentElement;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Scenario Simulator (FR-60 – FR-65, PRD §7.7, DECISIONS D-21 – D-26).
 *
 * Projection is pure computation over a baseline's scored elements: it never writes to
 * `assessments` or `assessment_elements` (FR-63). Saving persists only a `simulation_sessions` row.
 * The browser runs the same algorithm for live feedback (D-23); this class stays authoritative.
 */
class SimulationService
{
    public function __construct(private AuditService $audit) {}

    /**
     * Project the posture if the given elements were at the given levels.
     *
     * An element without an override keeps its baseline level and "implemented" status (≥ 1 Yes, D-21);
     * an Unanswered element without an override stays out of its domain average (D-22).
     *
     * @param  array<string, mixed>  $overrides  element code => hypothetical level 0..3
     */
    public function project(Assessment $baseline, array $overrides): SimulationResult
    {
        $elements = $this->elements($baseline);
        $overrides = $this->effectiveOverrides($elements, $overrides);

        $working = $elements->map(function (AssessmentElement $element) use ($overrides): array {
            $level = $overrides[$element->element_code] ?? null;

            return $level === null
                ? ['domain' => $element->domain, 'level' => $element->maturity_level?->value, 'implemented' => $element->yes_count >= 1]
                : ['domain' => $element->domain, 'level' => $level, 'implemented' => $level >= MaturityLevel::Basic->value];
        });

        $domainScores = [];
        $domainDeltas = [];
        foreach (Domain::cases() as $domain) {
            $levels = $working->where('domain', $domain)->pluck('level')->reject(fn (?int $level): bool => $level === null);
            $projected = $levels->isEmpty() ? null : round($levels->avg(), 2);
            $base = $baseline->{$domain->scoreColumn()};

            $domainScores[$domain->value] = $projected;
            $domainDeltas[$domain->value] = $projected === null || $base === null ? null : round($projected - (float) $base, 2);
        }

        $overallScore = round($working->where('implemented', true)->count() / config('ncsb.element_count'), 3);

        return new SimulationResult(
            projectedDomainScores: $domainScores,
            domainDeltas: $domainDeltas,
            projectedOverallScore: $overallScore,
            projectedBand: Band::fromScore($overallScore),
            overallDelta: round($overallScore - (float) $baseline->overall_score, 3),
            elementsAtTarget: $working->where('level', MaturityLevel::Target->value)->count(),
            overrides: $overrides,
        );
    }

    /**
     * Save a scenario (FR-64). The projection is recomputed here, never taken from the browser (D-23).
     *
     * @param  array<string, mixed>  $overrides
     */
    public function save(Assessment $baseline, array $overrides, string $scenarioName, User $user): SimulationSession
    {
        $result = $this->project($baseline, $overrides);

        return DB::transaction(function () use ($baseline, $result, $scenarioName, $user): SimulationSession {
            $session = SimulationSession::query()->create([
                'created_by' => $user->id,
                'baseline_assessment_id' => $baseline->id,
                'scenario_name' => $scenarioName,
                'modified_responses' => $result->overrides,
                'simulated_score' => $result->projectedOverallScore,
                'simulated_band' => $result->projectedBand,
            ]);

            $this->audit->log('simulation.save', AuditCategory::Compliance, $session, [
                'scenario_name' => $scenarioName,
                'baseline_cycle' => $baseline->cycle_label,
                'elements_changed' => count($result->overrides),
                'simulated_score' => $result->projectedOverallScore,
                'simulated_band' => $result->projectedBand->value,
            ], $user);

            return $session;
        });
    }

    /**
     * Authors may delete their own scenarios (DECISIONS D-26).
     */
    public function delete(SimulationSession $session, User $user): void
    {
        DB::transaction(function () use ($session, $user): void {
            $this->audit->log('simulation.deleted', AuditCategory::Compliance, $session, [
                'scenario_name' => $session->scenario_name,
                'baseline_cycle' => $session->baseline->cycle_label,
            ], $user);

            $session->delete();
        });
    }

    /**
     * The baseline as the slider list and the in-browser projection need it: elements in questionnaire order,
     * the six stored domain scores and the stored overall score (FR-61).
     *
     * @return array{elements: list<array{code: string, name: string, domain: string, level: ?int, implemented: bool}>, domains: list<array{value: string, label: string, area: string, score: ?float}>, overall_score: float, overall_band: string, elements_at_target: int}
     */
    public function baselineState(Assessment $baseline): array
    {
        $elements = $this->elements($baseline);

        return [
            'elements' => $elements
                ->map(fn (AssessmentElement $element): array => [
                    'code' => $element->element_code,
                    'name' => $element->ncsbElement->name,
                    'domain' => $element->domain->value,
                    'level' => $element->maturity_level?->value,
                    'implemented' => $element->yes_count >= 1,
                ])
                ->values()
                ->all(),
            'domains' => array_map(fn (Domain $domain): array => [
                'value' => $domain->value,
                'label' => $domain->label(),
                'area' => $domain->areaName(),
                'score' => $baseline->{$domain->scoreColumn()} === null ? null : (float) $baseline->{$domain->scoreColumn()},
            ], Domain::cases()),
            'overall_score' => (float) $baseline->overall_score,
            'overall_band' => $baseline->overall_band->value,
            'elements_at_target' => $elements->where('maturity_level', MaturityLevel::Target)->count(),
        ];
    }

    /**
     * Thresholds and colours for the in-browser projection, from the same enums the server uses (D-23).
     *
     * @return array{elementCount: int, bands: list<array{value: string, minimum: float, colour: string, textColour: string}>, risks: list<array{value: string, minimum: ?float, colour: string, textColour: string}>, levels: array<int|string, array{label: string, colour: string, textColour: string}>}
     */
    public function projectionRules(): array
    {
        $levels = [];
        foreach (MaturityLevel::cases() as $level) {
            $levels[$level->value] = ['label' => $level->label(), 'colour' => $level->severity()->colour(), 'textColour' => $level->severity()->textColour()];
        }
        $levels['unanswered'] = ['label' => Severity::Unanswered->label(), 'colour' => Severity::Unanswered->colour(), 'textColour' => Severity::Unanswered->textColour()];

        return [
            'elementCount' => (int) config('ncsb.element_count'),
            'bands' => array_map(fn (Band $band): array => [
                'value' => $band->value,
                'minimum' => $band->minimumScore(),
                'colour' => $band->severity()->colour(),
                'textColour' => $band->severity()->textColour(),
            ], Band::cases()),
            'risks' => array_map(fn (RiskCategory $risk): array => [
                'value' => $risk->value,
                'minimum' => $risk->minimumScore(),
                'colour' => $risk->severity()->colour(),
                'textColour' => $risk->severity()->textColour(),
            ], RiskCategory::cases()),
            'levels' => $levels,
        ];
    }

    /**
     * Drop unknown codes, clamp to 0..3, and drop a scored element set back to its baseline level,
     * so "no changes" always projects the baseline exactly (D-24). Unanswered elements keep any override (D-22).
     *
     * @param  EloquentCollection<int, AssessmentElement>  $elements
     * @param  array<string, mixed>  $overrides
     * @return array<string, int>
     */
    private function effectiveOverrides(EloquentCollection $elements, array $overrides): array
    {
        $effective = [];

        foreach ($elements as $element) {
            if (! array_key_exists($element->element_code, $overrides)) {
                continue;
            }

            $level = max(MaturityLevel::Initial->value, min(MaturityLevel::Target->value, (int) $overrides[$element->element_code]));

            if ($level !== $element->maturity_level?->value) {
                $effective[$element->element_code] = $level;
            }
        }

        return $effective;
    }

    /**
     * @return EloquentCollection<int, AssessmentElement>
     */
    private function elements(Assessment $baseline): EloquentCollection
    {
        return $baseline->elements()
            ->with('ncsbElement')
            ->join('ncsb_elements', 'assessment_elements.ncsb_element_id', '=', 'ncsb_elements.id')
            ->orderBy('ncsb_elements.sort_order')
            ->select('assessment_elements.*')
            ->get();
    }
}
