<?php

namespace Tests\Unit;

use App\Enums\Band;
use App\Enums\ComplianceStatus;
use App\Enums\Domain;
use App\Enums\MaturityLevel;
use App\Enums\RiskCategory;
use App\Enums\Severity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    /**
     * @return array<string, array{float, Band}>
     */
    public static function bandBoundaries(): array
    {
        return [
            '0.00' => [0.0, Band::Initial],
            '0.24' => [0.24, Band::Initial],
            '0.25' => [0.25, Band::Basic],
            '0.49' => [0.49, Band::Basic],
            '0.50' => [0.50, Band::Intermediate],
            '0.74' => [0.74, Band::Intermediate],
            '0.75' => [0.75, Band::Advanced],
            '1.00' => [1.0, Band::Advanced],
        ];
    }

    #[DataProvider('bandBoundaries')]
    public function test_band_from_overall_score(float $score, Band $expected): void
    {
        $this->assertSame($expected, Band::fromScore($score));
    }

    /**
     * @return array<string, array{float, RiskCategory}>
     */
    public static function riskBoundaries(): array
    {
        return [
            '0.00' => [0.0, RiskCategory::Critical],
            '0.99' => [0.99, RiskCategory::Critical],
            '1.00' => [1.0, RiskCategory::High],
            '1.74' => [1.74, RiskCategory::High],
            '1.75' => [1.75, RiskCategory::Moderate],
            '2.49' => [2.49, RiskCategory::Moderate],
            '2.50' => [2.50, RiskCategory::Acceptable],
            '3.00' => [3.0, RiskCategory::Acceptable],
        ];
    }

    #[DataProvider('riskBoundaries')]
    public function test_risk_category_from_domain_score(float $score, RiskCategory $expected): void
    {
        $this->assertSame($expected, RiskCategory::fromDomainScore($score));
    }

    public function test_maturity_level_gap_and_severity(): void
    {
        $this->assertSame([3, Severity::Critical], [MaturityLevel::Initial->gapFromTarget(), MaturityLevel::Initial->severity()]);
        $this->assertSame([2, Severity::High], [MaturityLevel::Basic->gapFromTarget(), MaturityLevel::Basic->severity()]);
        $this->assertSame([1, Severity::Low], [MaturityLevel::Intermediate->gapFromTarget(), MaturityLevel::Intermediate->severity()]);
        $this->assertSame([0, Severity::Compliant], [MaturityLevel::Advanced->gapFromTarget(), MaturityLevel::Advanced->severity()]);
    }

    public function test_domain_element_counts_sum_to_33_and_weights_favour_govern_and_respond(): void
    {
        $this->assertSame(33, array_sum(array_map(fn (Domain $domain) => $domain->elementCount(), Domain::cases())));
        $this->assertSame([Domain::Govern, Domain::Respond], array_values(array_filter(
            Domain::cases(),
            fn (Domain $domain) => $domain->riskWeight() === 2,
        )));
    }

    /**
     * DECISIONS D-58: a domain with no score is Not assessed, never Critical.
     */
    public function test_domain_without_a_score_is_not_assessed(): void
    {
        $this->assertSame(RiskCategory::NotAssessed, RiskCategory::fromDomainScore(null));
        $this->assertSame(Severity::Unanswered, RiskCategory::NotAssessed->severity());
    }

    /**
     * FR-31: no maturity numbers and no NCSB jargon in any narrative. The only number allowed is the Act's year.
     */
    public function test_narratives_contain_no_numbers_or_ncsb_jargon(): void
    {
        foreach (RiskCategory::cases() as $risk) {
            foreach (Domain::cases() as $domain) {
                $narrative = $risk->narrative($domain);

                $this->assertStringContainsStringIgnoringCase($domain->areaName(), $narrative);
                $this->assertDoesNotMatchRegularExpression('/\d/', str_replace('Cyber Security Act 2024', '', $narrative), $narrative);
                $this->assertDoesNotMatchRegularExpression('/\b(NCSB|level|maturity|element|domain|score|threshold)\b/i', $narrative, $narrative);
                $this->assertStringNotContainsString($domain->value, $narrative);
            }
        }
    }

    /**
     * @return array<string, array{Band, list<RiskCategory>, bool, ComplianceStatus}>
     */
    public static function complianceCases(): array
    {
        $tolerable = [RiskCategory::Acceptable, RiskCategory::Moderate, RiskCategory::Acceptable, RiskCategory::Moderate, RiskCategory::Acceptable, RiskCategory::Acceptable];

        return [
            'advanced, all tolerable, all answered' => [Band::Advanced, $tolerable, false, ComplianceStatus::Compliant],
            'advanced but an element unanswered' => [Band::Advanced, $tolerable, true, ComplianceStatus::PartiallyCompliant],
            'advanced with a high-risk domain' => [Band::Advanced, [RiskCategory::High, ...array_slice($tolerable, 1)], false, ComplianceStatus::PartiallyCompliant],
            'advanced with a domain not assessed' => [Band::Advanced, [RiskCategory::NotAssessed, ...array_slice($tolerable, 1)], true, ComplianceStatus::PartiallyCompliant],
            'intermediate, all tolerable' => [Band::Intermediate, $tolerable, false, ComplianceStatus::PartiallyCompliant],
            'any critical domain' => [Band::Advanced, [RiskCategory::Critical, ...array_slice($tolerable, 1)], false, ComplianceStatus::NonCompliant],
            'initial band' => [Band::Initial, $tolerable, false, ComplianceStatus::NonCompliant],
        ];
    }

    /**
     * @param  list<RiskCategory>  $risks
     */
    #[DataProvider('complianceCases')]
    public function test_cyber_security_act_status_follows_decision_d50(Band $band, array $risks, bool $hasUnanswered, ComplianceStatus $expected): void
    {
        $this->assertSame($expected, ComplianceStatus::determine($band, $risks, $hasUnanswered));
    }
}
