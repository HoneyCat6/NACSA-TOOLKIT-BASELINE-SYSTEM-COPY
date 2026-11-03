<?php

namespace App\Services;

use App\Enums\Domain;
use App\Enums\RiskCategory;
use App\Models\Assessment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only four-month domain outlook using the daily drift across the latest three cycles.
 */
class ForecastService
{
    public const AssessmentCount = 3;

    public const HorizonMonths = 4;

    /**
     * @param  Collection<int, Assessment>  $assessments  latest three cycles, in chronological order
     * @return array{date: ?Carbon, sources: list<array{label: string, date: string}>, unavailable: ?string, rows: list<array{area: string, current: ?float, current_risk: RiskCategory, estimate: ?float, estimated_risk: ?RiskCategory, direction: ?string, risk_change: ?string}>, improving: int, declining: int, stable: int}
     */
    public function outlook(Collection $assessments): array
    {
        $assessments = $assessments->values();
        $latest = $assessments->last();
        $date = $latest?->assessment_date->copy()->addMonthsNoOverflow(self::HorizonMonths);
        $result = [
            'date' => $date,
            'sources' => $assessments->map(fn (Assessment $assessment): array => [
                'label' => $assessment->cycle_label,
                'date' => $assessment->assessment_date->format('j M Y'),
            ])->all(),
            'unavailable' => null,
            'rows' => [],
            'improving' => 0,
            'declining' => 0,
            'stable' => 0,
        ];

        if ($assessments->count() < self::AssessmentCount) {
            return [...$result, 'unavailable' => 'At least three assessment cycles are needed to estimate the next assessment.'];
        }

        if ($assessments->contains(fn (Assessment $assessment): bool => $assessment->unanswered_count > 0
            || $assessment->defaulted_cell_count > 0 || ! empty($assessment->parse_warnings))) {
            return [...$result, 'unavailable' => 'The latest three assessments include unanswered or flagged responses. Three complete assessments without response warnings are needed for a comparable forecast.'];
        }

        foreach ($assessments->values() as $index => $assessment) {
            if ($index > 0 && $assessment->assessment_date <= $assessments[$index - 1]->assessment_date) {
                return [...$result, 'unavailable' => 'The latest three assessments need distinct, increasing assessment dates.'];
            }
        }

        $elapsedDays = $assessments->first()->assessment_date->diff($latest->assessment_date)->days;
        $horizonDays = $latest->assessment_date->diff($date)->days;

        foreach (Domain::cases() as $domain) {
            $scores = $assessments->pluck($domain->scoreColumn());
            $current = $latest->{$domain->scoreColumn()} === null ? null : (float) $latest->{$domain->scoreColumn()};
            $estimate = $scores->containsStrict(null) ? null : round(max(0, min(3,
                $current + ($current - (float) $scores->first()) / $elapsedDays * $horizonDays,
            )), 2);
            $currentRisk = RiskCategory::fromDomainScore($current);
            $estimatedRisk = $estimate === null ? null : RiskCategory::fromDomainScore($estimate);
            $direction = $estimate === null ? null : match (true) {
                $estimate > $current => 'Improving',
                $estimate < $current => 'Declining',
                default => 'Stable',
            };

            $result['rows'][] = [
                'area' => $domain->areaName(),
                'current' => $current,
                'current_risk' => $currentRisk,
                'estimate' => $estimate,
                'estimated_risk' => $estimatedRisk,
                'direction' => $direction,
                'risk_change' => $estimatedRisk === null || $estimatedRisk === $currentRisk
                    ? null : 'May move from '.$currentRisk->value.' to '.$estimatedRisk->value.' risk',
            ];

            if ($direction !== null) {
                $result[strtolower($direction)]++;
            }
        }

        return $result;
    }
}
