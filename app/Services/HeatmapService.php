<?php

namespace App\Services;

use App\Enums\Domain;
use App\Enums\MaturityLevel;
use App\Enums\Severity;
use App\Models\Assessment;
use App\Models\AssessmentElement;
use App\Models\SectorConstructScore;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Control Effectiveness Heatmap (FR-50 – FR-54).
 *
 * Read-only over already-scored `assessment_elements`; it recomputes nothing, so the grid
 * matches the scored data exactly (Sprint 4 exit criterion).
 */
class HeatmapService
{
    /**
     * The 33 elements grouped under their six domains in questionnaire order (FR-50).
     *
     * Each group also carries its control count and the mean level of its answered controls
     * (null when none are answered) for the function summary line — computed here, not in Blade.
     *
     * @return array<string, array{domain: Domain, count: int, average: ?float, cells: list<array{code: string, name: string, state: string, level: ?int, severity: Severity, label: string}>}>
     */
    public function grid(Assessment $cycle): array
    {
        $elements = $this->elements($cycle);
        $customScores = $cycle->loadMissing('sectorConstructScores.sectorConstruct')->sectorConstructScores
            ->sortBy('sectorConstruct.code');

        $grid = [];
        foreach (Domain::cases() as $domain) {
            $cells = $elements
                ->where('domain', $domain)
                ->map(fn (AssessmentElement $element): array => [
                    'code' => $element->element_code,
                    'name' => $element->ncsbElement->name,
                    'state' => $element->isDisabled() ? 'disabled' : 'core',
                    'level' => $element->maturity_level?->value,
                    'severity' => $element->severity(),
                    'label' => $element->isDisabled() ? 'Disabled (N/A)' : $element->severity()->label(),
                ])
                ->values()
                ->all();

            $activeCore = collect($cells)->where('state', 'core');
            $answered = $activeCore->pluck('level')->reject(fn (?int $level): bool => $level === null);
            $customCells = $customScores->filter(fn (SectorConstructScore $score): bool => $score->sectorConstruct->domain === $domain)
                ->map(fn (SectorConstructScore $score): array => [
                    'code' => $score->sectorConstruct->code,
                    'name' => $score->sectorConstruct->name,
                    'state' => 'sector',
                    'level' => $score->maturity_level?->value,
                    'severity' => $score->maturity_level?->severity() ?? Severity::Unanswered,
                    'label' => 'Sector-specific',
                ])->values()->all();

            $grid[$domain->value] = [
                'domain' => $domain,
                'count' => $activeCore->count(),
                'average' => $answered->isEmpty() ? null : round($answered->avg(), 2),
                'cells' => [...$cells, ...$customCells],
            ];
        }

        return $grid;
    }

    /**
     * All assessment cycles, newest first, for the historical-replay selector (FR-53).
     *
     * @return EloquentCollection<int, Assessment>
     */
    public function cycles(): EloquentCollection
    {
        return Assessment::query()->with('sector')->newestFirst()->get();
    }

    /**
     * Drill-down for a single element in a cycle (FR-51).
     *
     * @return array{code: string, name: string, domain: string, category: string, maturity_level: ?int, level_label: string, gap_from_target: ?int, severity: string, severity_label: string, yes_count: ?int, question_count: ?int, remediation_note: string}
     */
    public function detail(Assessment $cycle, string $code): array
    {
        $element = $cycle->elements()
            ->with('ncsbElement')
            ->where('element_code', $code)
            ->first();

        if ($element === null) {
            return $this->sectorDetail($cycle, $code);
        }

        $reference = $element->ncsbElement;
        $level = $element->maturity_level;

        return [
            'code' => $element->element_code,
            'name' => $reference->name,
            'domain' => $element->domain->label(),
            'category' => $reference->category,
            'maturity_level' => $level?->value,
            'level_label' => $element->isDisabled() ? 'N/A' : ($level?->label() ?? 'Unanswered'),
            'gap_from_target' => $level?->gapFromTarget(),
            'severity' => $element->severity()->value,
            'severity_label' => $element->isDisabled() ? 'Disabled (N/A)' : $element->severity()->label(),
            'yes_count' => $element->isDisabled() ? null : $element->yes_count,
            'question_count' => $element->isDisabled() ? null : $reference->question_count,
            'remediation_note' => $this->remediationNote($element),
        ];
    }

    /** @return array<string, int|string|null> */
    private function sectorDetail(Assessment $cycle, string $code): array
    {
        $score = $cycle->sectorConstructScores()->with('sectorConstruct')->whereRelation('sectorConstruct', 'code', $code)->firstOrFail();
        $construct = $score->sectorConstruct;
        $level = $score->maturity_level;

        return [
            'code' => $construct->code,
            'name' => $construct->name,
            'domain' => $construct->domain->label(),
            'category' => 'Sector-specific',
            'maturity_level' => $level?->value,
            'level_label' => $level?->label() ?? 'Unassessed',
            'gap_from_target' => $level?->gapFromTarget(),
            'severity' => ($level?->severity() ?? Severity::Unanswered)->value,
            'severity_label' => 'Sector-specific',
            'yes_count' => null,
            'question_count' => null,
            'remediation_note' => $construct->requirement,
        ];
    }

    /**
     * The concrete requirement for the next level up (FR-51), or the compliant / unassessed message.
     */
    private function remediationNote(AssessmentElement $element): string
    {
        $level = $element->maturity_level;

        if ($element->isDisabled()) {
            return 'This core construct was marked N/A for this assessment.';
        }

        if ($level === null) {
            return 'Assess this element to determine its maturity.'; // D-58
        }

        if ($level === MaturityLevel::Advanced) {
            return 'Compliant — meets the Level 3 target.';
        }

        $next = MaturityLevel::from($level->value + 1);

        return "To reach Level {$next->value} ({$next->label()}): ".$element->ncsbElement->requirementFor($next);
    }

    /**
     * @return EloquentCollection<int, AssessmentElement>
     */
    private function elements(Assessment $cycle): EloquentCollection
    {
        return $cycle->elements()
            ->with('ncsbElement')
            ->join('ncsb_elements', 'assessment_elements.ncsb_element_id', '=', 'ncsb_elements.id')
            ->orderBy('ncsb_elements.sort_order')
            ->select('assessment_elements.*')
            ->get();
    }
}
