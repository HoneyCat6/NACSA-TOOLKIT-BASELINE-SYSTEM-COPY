<?php

namespace App\Services;

use App\DataObjects\ParsedAssessment;
use App\DataObjects\ScoredAssessment;
use App\DataObjects\ScoredElement;
use App\Enums\Band;
use App\Enums\Domain;
use App\Enums\ElementStatus;
use App\Enums\MaturityLevel;
use App\Models\Assessment;
use App\Models\NcsbElement;
use InvalidArgumentException;

/**
 * Element, domain and overall maturity (PRD §7.3, DECISIONS D-20).
 *
 * The only writer of `assessments` / `assessment_elements` (TECH_SPEC §3.3).
 */
class ScoringService
{
    public function score(ParsedAssessment $parsed): ScoredAssessment
    {
        $elements = [];

        foreach (NcsbElement::query()->orderBy('sort_order')->get() as $reference) {
            $responses = $parsed->responsesByElement[$reference->code]
                ?? throw new InvalidArgumentException("No responses for element {$reference->code}.");

            $yesCount = count(array_filter($responses, fn (?bool $answer): bool => $answer === true));
            $isUnanswered = in_array($reference->code, $parsed->unansweredCodes, true);

            $elements[$reference->code] = new ScoredElement(
                ncsbElementId: $reference->id,
                code: $reference->code,
                domain: $reference->domain,
                yesCount: $yesCount,
                level: $isUnanswered ? null : $this->levelFor($yesCount, $reference),
                status: $isUnanswered ? ElementStatus::Unanswered : ElementStatus::Scored,
                responses: $responses,
            );
        }

        $overallScore = $this->overallScore($elements);

        return new ScoredAssessment(
            elements: $elements,
            domainScores: $this->domainScores($elements),
            overallScore: $overallScore,
            overallBand: Band::fromScore($overallScore),
            unansweredCount: count($parsed->unansweredCodes),
            defaultedCellCount: $parsed->defaultedCellCount,
            warnings: $parsed->warnings,
        );
    }

    /**
     * Persist a scored assessment and its 33 element rows. Call inside a transaction.
     *
     * @param  array{created_by: int, assessment_date: mixed, cycle_label: string, original_filename: string, stored_path: string, file_hash: string}  $attributes
     */
    public function persist(ScoredAssessment $scored, array $attributes): Assessment
    {
        $domainScores = [];
        foreach (Domain::cases() as $domain) {
            $domainScores[$domain->scoreColumn()] = $scored->domainScores[$domain->value];
        }

        $assessment = Assessment::query()->create([
            ...$attributes,
            ...$domainScores,
            'overall_score' => $scored->overallScore,
            'overall_band' => $scored->overallBand,
            'unanswered_count' => $scored->unansweredCount,
            'defaulted_cell_count' => $scored->defaultedCellCount,
            'parse_warnings' => $scored->warnings === [] ? null : $scored->warnings,
        ]);

        $assessment->elements()->createMany(array_map(fn (ScoredElement $element): array => [
            'ncsb_element_id' => $element->ncsbElementId,
            'domain' => $element->domain,
            'element_code' => $element->code,
            'yes_count' => $element->yesCount,
            'maturity_level' => $element->level,
            'status' => $element->status,
            'responses' => $element->responses,
        ], array_values($scored->elements)));

        return $assessment;
    }

    /**
     * Thresholds come from the toolkit's Scoring Dashboard formulas (D-15).
     */
    private function levelFor(int $yesCount, NcsbElement $reference): MaturityLevel
    {
        return match (true) {
            $yesCount >= $reference->threshold_l3 => MaturityLevel::Advanced,
            $yesCount >= $reference->threshold_l2 => MaturityLevel::Intermediate,
            $yesCount >= $reference->threshold_l1 => MaturityLevel::Basic,
            default => MaturityLevel::Initial,
        };
    }

    /**
     * Domain score = mean level of the domain's scored elements, 0.00–3.00. Unanswered elements are left out.
     *
     * @param  array<string, ScoredElement>  $elements
     * @return array<string, ?float>
     */
    private function domainScores(array $elements): array
    {
        $scores = [];

        foreach (Domain::cases() as $domain) {
            $levels = collect($elements)
                ->filter(fn (ScoredElement $element): bool => $element->domain === $domain && $element->level !== null)
                ->map(fn (ScoredElement $element): int => $element->level->value);

            $scores[$domain->value] = $levels->isEmpty() ? null : round($levels->avg(), 2);
        }

        return $scores;
    }

    /**
     * Overall = elements with at least one "Yes" ÷ 33, as the toolkit's `COUNTIF(D3:D35,">0")/33` (D-20).
     *
     * @param  array<string, ScoredElement>  $elements
     */
    private function overallScore(array $elements): float
    {
        $implemented = collect($elements)->filter(fn (ScoredElement $element): bool => $element->yesCount >= 1)->count();

        return round($implemented / config('ncsb.element_count'), 3);
    }
}
