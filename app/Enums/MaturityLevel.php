<?php

namespace App\Enums;

enum MaturityLevel: int
{
    case Initial = 0;
    case Basic = 1;
    case Intermediate = 2;
    case Advanced = 3;

    public const Target = self::Advanced;

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial',
            self::Basic => 'Basic',
            self::Intermediate => 'Intermediate',
            self::Advanced => 'Advanced',
        };
    }

    /**
     * Gap from the Level 3 target — drives heatmap severity and remediation ranking.
     */
    public function gapFromTarget(): int
    {
        return self::Target->value - $this->value;
    }

    public function severity(): Severity
    {
        return match ($this->gapFromTarget()) {
            0 => Severity::Compliant,
            1 => Severity::Low,
            2 => Severity::High,
            default => Severity::Critical,
        };
    }
}
