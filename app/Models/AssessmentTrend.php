<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class AssessmentTrend extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'govern_delta' => 'decimal:2',
            'identify_delta' => 'decimal:2',
            'protect_delta' => 'decimal:2',
            'detect_delta' => 'decimal:2',
            'respond_delta' => 'decimal:2',
            'recover_delta' => 'decimal:2',
            'overall_delta' => 'decimal:3',
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
     * @return BelongsTo<Assessment, $this>
     */
    public function previousAssessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'prev_assessment_id');
    }

    /**
     * @return HasMany<DegradationAlert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(DegradationAlert::class);
    }
}
