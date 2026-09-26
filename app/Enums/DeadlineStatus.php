<?php

namespace App\Enums;

enum DeadlineStatus: string
{
    case Upcoming = 'upcoming';
    case DueSoon = 'due_soon';
    case Overdue = 'overdue';
    case Met = 'met';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Upcoming',
            self::DueSoon => 'Due Soon',
            self::Overdue => 'Overdue',
            self::Met => 'Met',
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
