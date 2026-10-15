<?php

namespace App\Enums;

/**
 * Indicative Cyber Security Act 2024 compliance status (DECISIONS D-50). Not a legal determination.
 */
enum ComplianceStatus: string
{
    case Compliant = 'Compliant';
    case PartiallyCompliant = 'Partially compliant';
    case NonCompliant = 'Non-compliant';

    /**
     * D-50: Non-compliant if any domain is Critical or the band is Initial; Compliant if the band is Advanced,
     * every domain is Moderate or Acceptable and no element is unanswered; otherwise Partially compliant.
     *
     * @param  iterable<RiskCategory>  $domainRisks
     */
    public static function determine(Band $overallBand, iterable $domainRisks, bool $hasUnansweredElements): self
    {
        $risks = collect($domainRisks);

        if ($overallBand === Band::Initial || $risks->contains(RiskCategory::Critical)) {
            return self::NonCompliant;
        }

        $everyDomainTolerable = $risks->every(
            fn (RiskCategory $risk): bool => in_array($risk, [RiskCategory::Moderate, RiskCategory::Acceptable], true),
        );

        if ($overallBand === Band::Advanced && $everyDomainTolerable && ! $hasUnansweredElements) {
            return self::Compliant;
        }

        return self::PartiallyCompliant;
    }

    public function summary(): string
    {
        return match ($this) {
            self::Compliant => 'The baseline is established and every area meets at least a moderate standard.',
            self::PartiallyCompliant => 'Baseline controls are in place, but some areas carry high risk or have not been fully assessed.',
            self::NonCompliant => 'Critical gaps remain, or the baseline is largely not yet established.',
        };
    }

    public function severity(): Severity
    {
        return match ($this) {
            self::Compliant => Severity::Compliant,
            self::PartiallyCompliant => Severity::Low,
            self::NonCompliant => Severity::Critical,
        };
    }
}
