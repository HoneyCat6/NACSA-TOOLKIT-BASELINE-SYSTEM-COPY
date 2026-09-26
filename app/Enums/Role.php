<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case ComplianceOfficer = 'compliance_officer';
    case ExecutiveReviewer = 'executive_reviewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::ComplianceOfficer => 'Compliance Officer',
            self::ExecutiveReviewer => 'Executive Reviewer',
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
