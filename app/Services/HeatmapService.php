<?php

namespace App\Services;

use App\Enums\Domain;
use App\Enums\MaturityLevel;
use App\Enums\Severity;
use App\Models\Assessment;
use App\Models\AssessmentElement;
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
     * @return array<string, array{domain: Domain, count: int, average: ?float, cells: list<array{code: string, name: string, level: ?int, severity: Severity, label: string}>}>
     */
    public function grid(Assessment $cycle): array
    {
        $elements = $this->elements($cycle);

        $grid = [];
        foreach (Domain::cases() as $domain) {
            $cells = $elements
                ->where('domain', $domain)
                ->map(fn (AssessmentElement $element): array => [
                    'code' => $element->element_code,
                    'name' => $element->ncsbElement->name,
                    'level' => $element->maturity_level?->value,
                    'severity' => $element->severity(),
                    'label' => $element->severity()->label(),
                ])
                ->values()
                ->all();

            $answered = collect($cells)->pluck('level')->reject(fn (?int $level): bool => $level === null);

            $grid[$domain->value] = [
                'domain' => $domain,
                'count' => count($cells),
                'average' => $answered->isEmpty() ? null : round($answered->avg(), 2),
                'cells' => $cells,
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
        return Assessment::query()->newestFirst()->get();
    }

    /**
     * Drill-down for a single element in a cycle (FR-51).
     *
     * @return array{code: string, name: string, domain: string, category: string, maturity_level: ?int, level_label: string, gap_from_target: ?int, severity: string, severity_label: string, yes_count: int, question_count: int, remediation_note: string}
     */
    public function detail(Assessment $cycle, string $code): array
    {
        $element = $cycle->elements()
            ->with('ncsbElement')
            ->where('element_code', $code)
            ->firstOrFail();

        $reference = $element->ncsbElement;
        $level = $element->maturity_level;

        return [
            'code' => $element->element_code,
            'name' => $reference->name,
            'domain' => $element->domain->label(),
            'category' => $reference->category,
            'maturity_level' => $level?->value,
            'level_label' => $level?->label() ?? 'Unanswered',
            'gap_from_target' => $level?->gapFromTarget(),
            'severity' => $element->severity()->value,
            'severity_label' => $element->severity()->label(),
            'yes_count' => $element->yes_count,
            'question_count' => $reference->question_count,
            'remediation_note' => $this->remediationNote($element),
        ];
    }

    /**
     * The concrete requirement for the next level up (FR-51), or the compliant / unassessed message.
     */
    private function remediationNote(AssessmentElement $element): string
    {
        $level = $element->maturity_level;

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
