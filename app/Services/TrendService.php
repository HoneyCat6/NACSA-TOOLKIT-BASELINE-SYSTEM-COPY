<?php

namespace App\Services;

use App\Enums\Domain;
use App\Models\Assessment;
use App\Models\AssessmentTrend;
use App\Models\DegradationAlert;
use Illuminate\Support\Collection;

/**
 * Cross-cycle deltas and degradation detection (PRD §7.4, FR-18, FR-19, FR-21).
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

        return AssessmentTrend::query()->create([
            'assessment_id' => $current->id,
            'prev_assessment_id' => $previous->id,
            'overall_delta' => round((float) $current->overall_score - (float) $previous->overall_score, 3),
            ...$deltas,
        ]);
    }

    /**
     * One alert per domain whose score fell. A domain missing from either cycle is skipped.
     *
     * @return Collection<int, DegradationAlert>
     */
    public function detectDegradation(AssessmentTrend $trend): Collection
    {
        return collect(Domain::cases())
            ->filter(fn (Domain $domain): bool => $this->isDeclining($trend, $domain))
            ->map(fn (Domain $domain): DegradationAlert => $trend->alerts()->create([
                'domain' => $domain,
                'score_drop' => abs((float) $trend->{$domain->deltaColumn()}),
                'triggered_at' => now(),
            ]))
            ->values();
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

    private function toFloat(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
