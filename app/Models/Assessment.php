<?php

namespace App\Models;

use App\Enums\Band;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Guarded(['id'])]
class Assessment extends Model
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
            'assessment_date' => 'date',
            'overall_score' => 'decimal:3',
            'overall_band' => Band::class,
            'govern_score' => 'decimal:2',
            'identify_score' => 'decimal:2',
            'protect_score' => 'decimal:2',
            'detect_score' => 'decimal:2',
            'respond_score' => 'decimal:2',
            'recover_score' => 'decimal:2',
            'parse_warnings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AssessmentElement, $this>
     */
    public function elements(): HasMany
    {
        return $this->hasMany(AssessmentElement::class);
    }

    /**
     * @return HasOne<AssessmentTrend, $this>
     */
    public function trend(): HasOne
    {
        return $this->hasOne(AssessmentTrend::class);
    }

    /**
     * @return HasMany<ExecutiveReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ExecutiveReport::class);
    }

    /**
     * The cycle immediately before this one.
     */
    public function previous(): ?self
    {
        return static::query()
            ->where('assessment_date', '<', $this->assessment_date)
            ->newestFirst()
            ->first();
    }

    /**
     * Only the most recent cycle can be deleted, so no other cycle's trend ever needs recomputing (DECISIONS D-35).
     */
    public function isLatest(): bool
    {
        return static::query()->newestFirst()->value('id') === $this->id;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function chronological(Builder $query): void
    {
        $query->orderBy('assessment_date')->orderBy('id');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function newestFirst(Builder $query): void
    {
        $query->orderByDesc('assessment_date')->orderByDesc('id');
    }
}
