<?php

namespace App\Enums;

enum Band: string
{
    case Initial = 'Initial';
    case Basic = 'Basic';
    case Intermediate = 'Intermediate';
    case Advanced = 'Advanced';

    /**
     * Map an overall score (0.00–1.00) to a band (PRD §7.1, toolkit Scoring Dashboard K2).
     */
    public static function fromScore(float $score): self
    {
        return match (true) {
            $score >= 0.75 => self::Advanced,
            $score >= 0.50 => self::Intermediate,
            $score >= 0.25 => self::Basic,
            default => self::Initial,
        };
    }

    /**
     * Shares the heatmap colour semantics so every surface uses one palette (PRD §11).
     */
    public function severity(): Severity
    {
        return match ($this) {
            self::Initial => Severity::Critical,
            self::Basic => Severity::High,
            self::Intermediate => Severity::Low,
            self::Advanced => Severity::Compliant,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
