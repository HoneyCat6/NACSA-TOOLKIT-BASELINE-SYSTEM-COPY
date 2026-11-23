<?php

namespace App\Models;

use App\Enums\Domain;
use App\Enums\ElementStatus;
use App\Enums\MaturityLevel;
use App\Enums\Severity;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class AssessmentElement extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responses' => 'encrypted:array',
            'maturity_level' => MaturityLevel::class,
            'level_delta' => 'integer',
            'domain' => Domain::class,
            'status' => ElementStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<NcsbElement, $this>
     */
    public function ncsbElement(): BelongsTo
    {
        return $this->belongsTo(NcsbElement::class);
    }

    public function isDisabled(): bool
    {
        return $this->status === ElementStatus::Disabled;
    }

    /**
     * Elements without a maturity level render neutral grey (FR-52).
     */
    public function severity(): Severity
    {
        return $this->maturity_level?->severity() ?? Severity::Unanswered;
    }
}
