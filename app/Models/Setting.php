<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable key–value settings (DECISIONS D-51).
 */
#[Guarded([])]
#[Table(key: 'key', keyType: 'string', incrementing: false)]
class Setting extends Model
{
    public const OrganisationName = 'organisation_name';

    public const ReportFooter = 'report_footer';

    public static function valueOf(string $key, ?string $default = null): ?string
    {
        return static::query()->find($key)?->value ?? $default;
    }

    public static function store(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
