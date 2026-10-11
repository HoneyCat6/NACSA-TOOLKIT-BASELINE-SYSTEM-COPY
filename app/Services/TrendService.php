<?php

namespace App\Services;

use App\Enums\Domain;
use App\Models\Assessment;
use App\Models\AssessmentElement;
use App\Models\AssessmentTrend;
use App\Models\DegradationAlert;
use App\Models\NcsbElement;
use Illuminate\Support\Collection;

/**
 * Cross-cycle deltas and degradation detection, per domain and per element (PRD §7.4, FR-18, FR-19, FR-21, DECISIONS D-36).
 */
class TrendService
{
    /**
     * Deltas against the cycle immediately before this one, or null for the first cycle.
     */
    public function computeTrend(Assessment $current): ?AssessmentTrend
    {
        $previous = $current->previous();

        if ($previous === null) {
            return null;
        }

        $deltas = [];
        foreach (Domain::cases() as $domain) {
            $currentScore = $current->{$domain->scoreColumn()};
            $previousScore = $previous->{$domain->scoreColumn()};

            $deltas[$domain->deltaColumn()] = $currentScore === null || $previousScore === null
                ? null
                : round((float) $currentScore - (float) $previousScore, 2);
        }

        $this->recordElementDeltas($current, $previous);

        return AssessmentTrend::query()->create([
            'assessment_id' => $current->id,
            'prev_assessment_id' => $previous->id,
            'overall_delta' => round((float) $current->overall_score - (float) $previous->overall_score, 3),
            ...$deltas,
        ]);
    }

    /**
     * One alert per domain whose score fell, then one per element whose level fell (D-36).
     * A domain or element missing from either cycle is skipped.
     *
     * @return Collection<int, DegradationAlert>
     */
    public function detectDegradation(AssessmentTrend $trend): Collection
    {
        $domainAlerts = collect(Domain::cases())
            ->filter(fn (Domain $domain): bool => $this->isDeclining($trend, $domain))
            ->map(fn (Domain $domain): DegradationAlert => $trend->alerts()->create([
                'domain' => $domain,
                'score_drop' => abs((float) $trend->{$domain->deltaColumn()}),
                'triggered_at' => now(),
            ]));

        $elementAlerts = AssessmentElement::query()
            ->join('ncsb_elements', 'ncsb_elements.id', '=', 'assessment_elements.ncsb_element_id')
            ->where('assessment_elements.assessment_id', $trend->assessment_id)
            ->where('assessment_elements.level_delta', '<', 0)
            ->orderBy('ncsb_elements.sort_order')
            ->get(['assessment_elements.domain', 'assessment_elements.ncsb_element_id', 'assessment_elements.level_delta'])
            ->map(fn (AssessmentElement $element): DegradationAlert => $trend->alerts()->create([
                'domain' => $element->domain,
                'ncsb_element_id' => $element->ncsb_element_id,
                'score_drop' => abs($element->level_delta),
                'triggered_at' => now(),
            ]));

        return $domainAlerts->concat($elementAlerts)->values();
    }

    public function isDeclining(AssessmentTrend $trend, Domain $domain): bool
    {
        $delta = $trend->{$domain->deltaColumn()};

        return $delta !== null && (float) $delta < 0;
    }

    /**
     * Chart.js payload for the trend dashboard (TECH_SPEC §11.3). A series is "declining" when the latest cycle fell.
     *
     * @param  Collection<int, Assessment>  $assessments  in chronological order, with `trend` loaded
     * @return array{labels: list<string>, datasets: list<array{label: string, data: list<?float>, declining: bool}>, overall: list<float>}
     */
    public function chartData(Collection $assessments): array
    {
        $latestTrend = $assessments->last()?->trend;

        return [
            'labels' => $assessments->pluck('cycle_label')->all(),
            'datasets' => array_map(fn (Domain $domain): array => [
                'label' => $domain->value,
                'data' => $assessments->map(fn (Assessment $assessment): ?float => $this->toFloat($assessment->{$domain->scoreColumn()}))->all(),
                'declining' => $latestTrend !== null && $this->isDeclining($latestTrend, $domain),
            ], Domain::cases()),
            'overall' => $assessments->map(fn (Assessment $assessment): float => (float) $assessment->overall_score)->all(),
        ];
    }

    /**
     * Level of each of the 33 elements in every cycle, for the trend dashboard (D-36, proposal Table 5.2 trend coverage).
     *
     * @param  Collection<int, Assessment>  $assessments  in chronological order
     * @return list<array{code: string, name: string, domain: Domain, levels: list<?int>, latest_delta: ?int}>
     */
    public function elementHistory(Collection $assessments): array
    {
        $rows = AssessmentElement::query()
            ->toBase()
            ->whereIn('assessment_id', $assessments->pluck('id'))
            ->get(['assessment_id', 'element_code', 'maturity_level', 'level_delta'])
            ->groupBy('element_code');

        $latestId = $assessments->last()?->id;

        return NcsbElement::query()->orderBy('sort_order')->get()
            ->map(function (NcsbElement $element) use ($rows, $assessments, $latestId): array {
                $byAssessment = ($rows[$element->code] ?? collect())->keyBy('assessment_id');

                return [
                    'code' => $element->code,
                    'name' => $element->name,
                    'domain' => $element->domain,
                    'levels' => $assessments
                        ->map(fn (Assessment $assessment): ?int => $this->toInt($byAssessment[$assessment->id]->maturity_level ?? null))
                        ->values()
                        ->all(),
                    'latest_delta' => $this->toInt($byAssessment[$latestId]->level_delta ?? null),
                ];
            })
            ->all();
    }

    /**
     * Level change of every element against the previous cycle; null when either cycle left it unanswered.
     */
    private function recordElementDeltas(Assessment $current, Assessment $previous): void
    {
        $previousLevels = $previous->elements()->toBase()->pluck('maturity_level', 'element_code');

        foreach ($current->elements()->get(['id', 'element_code', 'maturity_level']) as $element) {
            $previousLevel = $previousLevels[$element->element_code] ?? null;

            if ($element->maturity_level !== null && $previousLevel !== null) {
                $element->update(['level_delta' => $element->maturity_level->value - (int) $previousLevel]);
            }
        }
    }

    private function toInt(int|string|null $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function toFloat(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
