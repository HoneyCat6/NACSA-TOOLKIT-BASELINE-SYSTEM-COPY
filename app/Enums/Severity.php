<?php

namespace App\Enums;

enum Severity: string
{
    case Compliant = 'compliant';
    case Low = 'low';
    case High = 'high';
    case Critical = 'critical';
    case Unanswered = 'unanswered';

    public function label(): string
    {
        return match ($this) {
            self::Compliant => 'Compliant',
            self::Low => 'Low Risk',
            self::High => 'High Risk',
            self::Critical => 'Critical Risk',
            self::Unanswered => 'Unanswered',
        };
    }

    /**
     * Bootstrap palette. Colour is never the only signal (FR-54).
     */
    public function colour(): string
    {
        return match ($this) {
            self::Compliant => '#198754',
            self::Low => '#ffc107',
            self::High => '#fd7e14',
            self::Critical => '#dc3545',
            self::Unanswered => '#adb5bd',
        };
    }
}
