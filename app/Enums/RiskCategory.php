<?php

namespace App\Enums;

enum RiskCategory: string
{
    case Critical = 'Critical';
    case High = 'High';
    case Moderate = 'Moderate';
    case Acceptable = 'Acceptable';
    case NotAssessed = 'Not assessed';

    /**
     * Map a domain score (0.00–3.00) to a business-risk category (PRD §7.6).
     *
     * A domain with no score (every element Unanswered) is Not assessed, never Critical (DECISIONS D-58).
     */
    public static function fromDomainScore(?float $score): self
    {
        if ($score === null) {
            return self::NotAssessed;
        }

        foreach ([self::Acceptable, self::Moderate, self::High] as $category) {
            if ($score >= $category->minimumScore()) {
                return $category;
            }
        }

        return self::Critical;
    }

    /**
     * Lowest domain score in this category; null for Not assessed. Also handed to the simulator (DECISIONS D-23).
     */
    public function minimumScore(): ?float
    {
        return match ($this) {
            self::Critical => 0.0,
            self::High => 1.00,
            self::Moderate => 1.75,
            self::Acceptable => 2.50,
            self::NotAssessed => null,
        };
    }

    /**
     * Board-readable narrative for one domain (PRD §7.6, FR-31): no maturity numbers, no NCSB jargon.
     */
    public function narrative(Domain $domain): string
    {
        $area = $domain->areaName();

        return match ($this) {
            self::Critical => 'Controls in '.lcfirst($area).' are largely absent. Operational disruption is likely and the organisation is materially exposed under the Cyber Security Act 2024.',
            self::High => "{$area} controls exist but are inconsistently applied. A disruptive incident would probably exceed the organisation's current response capacity.",
            self::Moderate => "{$area} controls are established but not yet optimised. Residual risk is tolerable but should be reduced within the next assessment cycle.",
            self::Acceptable => "{$area} controls meet the baseline. Maintain current practice and monitor for degradation.",
            self::NotAssessed => "{$area} was not assessed in this cycle, so the organisation's exposure in this area cannot be judged. Complete this part of the self-assessment before relying on this report.",
        };
    }

    /**
     * Shares the heatmap palette so every surface uses one set of colours (PRD §11).
     */
    public function severity(): Severity
    {
        return match ($this) {
            self::Critical => Severity::Critical,
            self::High => Severity::High,
            self::Moderate => Severity::Low,
            self::Acceptable => Severity::Compliant,
            self::NotAssessed => Severity::Unanswered,
        };
    }
}
