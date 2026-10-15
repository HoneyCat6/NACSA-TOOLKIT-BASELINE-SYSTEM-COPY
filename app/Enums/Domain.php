<?php

namespace App\Enums;

enum Domain: string
{
    case Govern = 'GOVERN';
    case Identify = 'IDENTIFY';
    case Protect = 'PROTECT';
    case Detect = 'DETECT';
    case Respond = 'RESPOND';
    case Recover = 'RECOVER';

    /**
     * Element count per domain (PRD Table 4.2), summing to 33.
     */
    public function elementCount(): int
    {
        return match ($this) {
            self::Govern => 7,
            self::Identify, self::Detect, self::Recover => 2,
            self::Protect => 19,
            self::Respond => 1,
        };
    }

    /**
     * Tie-break weight for remediation ranking (PRD §7.5, R8 — author design decision).
     */
    public function riskWeight(): int
    {
        return match ($this) {
            self::Govern, self::Respond => 2,
            default => 1,
        };
    }

    public function elementCodePrefix(): string
    {
        return match ($this) {
            self::Govern => 'GV',
            self::Identify => 'ID',
            self::Protect => 'PR',
            self::Detect => 'DE',
            self::Respond => 'RS',
            self::Recover => 'RC',
        };
    }

    public function label(): string
    {
        return ucfirst(strtolower($this->value));
    }

    /**
     * Plain-language name used in the executive report instead of NCSB terms (FR-31, DECISIONS D-57).
     */
    public function areaName(): string
    {
        return match ($this) {
            self::Govern => 'Cybersecurity governance',
            self::Identify => 'Asset and risk identification',
            self::Protect => 'Protective security',
            self::Detect => 'Threat detection',
            self::Respond => 'Incident response',
            self::Recover => 'Recovery and continuity',
        };
    }

    /**
     * Column on `assessments` holding this domain's 0–3 score.
     */
    public function scoreColumn(): string
    {
        return strtolower($this->value).'_score';
    }

    /**
     * Column on `assessment_trends` holding this domain's signed delta.
     */
    public function deltaColumn(): string
    {
        return strtolower($this->value).'_delta';
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
