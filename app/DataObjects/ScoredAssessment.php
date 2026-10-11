<?php

namespace App\DataObjects;

use App\Enums\Band;

final readonly class ScoredAssessment
{
    /**
     * @param  array<string, ScoredElement>  $elements  keyed by element code, in questionnaire order
     * @param  array<string, ?float>  $domainScores  domain value => mean element level 0.00–3.00, null when no element is scored
     * @param  float  $overallScore  share of the 33 elements with at least one "Yes", 0.000–1.000
     * @param  list<string>  $warnings  parse warnings carried through for the results screen
     */
    public function __construct(
        public array $elements,
        public array $domainScores,
        public float $overallScore,
        public Band $overallBand,
        public int $unansweredCount,
        public int $defaultedCellCount,
        public array $warnings,
    ) {}
}
