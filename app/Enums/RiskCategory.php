<?php

namespace App\Enums;

enum RiskCategory: string
{
    case Critical = 'Critical';
    case High = 'High';
    case Moderate = 'Moderate';
    case Acceptable = 'Acceptable';

    /**
     * Map a domain score (0.00–3.00) to a business-risk category (PRD §7.6).
     */
    public static function fromDomainScore(float $score): self
    {
        return match (true) {
            $score >= 2.50 => self::Acceptable,
            $score >= 1.75 => self::Moderate,
            $score >= 1.00 => self::High,
            default => self::Critical,
        };
    }
}
