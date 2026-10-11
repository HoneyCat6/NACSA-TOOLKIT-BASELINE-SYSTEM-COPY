<?php

namespace App\DataObjects;

/**
 * Raw responses extracted from a validated NCSB v1.4 workbook (PRD §7.2).
 */
final readonly class ParsedAssessment
{
    /**
     * @param  array<string, list<?bool>>  $responsesByElement  element code => answers in question order (true = Yes, false = No, null = blank)
     * @param  list<string>  $unansweredCodes  element codes whose questions are all blank (FR-13)
     * @param  int  $defaultedCellCount  blank cells before the first "No" in an element, treated as No (D-14)
     * @param  list<string>  $warnings  non-fatal parse warnings (D-12, D-13)
     */
    public function __construct(
        public array $responsesByElement,
        public array $unansweredCodes,
        public int $defaultedCellCount,
        public array $warnings,
    ) {}
}
