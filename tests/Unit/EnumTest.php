<?php

namespace Tests\Unit;

use App\Enums\Band;
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
}
