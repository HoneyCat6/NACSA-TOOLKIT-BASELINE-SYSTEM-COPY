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
        foreach (array_reverse(self::cases()) as $band) {
            if ($score >= $band->minimumScore()) {
                return $band;
            }
        }

        return self::Initial;
    }

    /**
     * Lowest overall score in this band — also handed to the simulator's in-browser projection (DECISIONS D-23).
     */
    public function minimumScore(): float
    {
        return match ($this) {
            self::Initial => 0.0,
            self::Basic => 0.25,
            self::Intermediate => 0.50,
            self::Advanced => 0.75,
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
