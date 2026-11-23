<?php

namespace App\DataObjects;

use App\Enums\Band;

final readonly class SimulationResult
{
    /**
     * @param  array<string, ?float>  $projectedDomainScores  domain value => mean element level 0.00–3.00, null when no element counts
     * @param  array<string, ?float>  $domainDeltas  domain value => signed change vs baseline, null when either side is null
     * @param  ?float  $projectedOverallScore  share of applicable core elements implemented, 0.000–1.000
     * @param  ?float  $overallDelta  signed change vs the baseline overall score
     * @param  int  $elementsAtTarget  elements at Level 3
     * @param  array<string, int>  $overrides  the effective overrides the projection used (DECISIONS D-24)
     */
    public function __construct(
        public array $projectedDomainScores,
        public array $domainDeltas,
        public ?float $projectedOverallScore,
        public Band $projectedBand,
        public ?float $overallDelta,
        public int $elementsAtTarget,
        public array $overrides,
    ) {}

    /**
     * The `projected` block of the `POST /simulator/project` contract (TECH_SPEC §11.1).
     *
     * @return array{domain_scores: array<string, ?float>, domain_deltas: array<string, ?float>, overall_score: ?float, overall_band: string, overall_delta: ?float, elements_at_target: int}
     */
    public function toArray(): array
    {
        return [
            'domain_scores' => $this->projectedDomainScores,
            'domain_deltas' => $this->domainDeltas,
            'overall_score' => $this->projectedOverallScore,
            'overall_band' => $this->projectedBand->value,
            'overall_delta' => $this->overallDelta,
            'elements_at_target' => $this->elementsAtTarget,
        ];
    }
}
