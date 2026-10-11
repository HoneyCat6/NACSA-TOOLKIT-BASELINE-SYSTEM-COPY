<?php

namespace App\Models;

use App\Enums\Domain;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class DegradationAlert extends Model
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
            'domain' => Domain::class,
            'score_drop' => 'decimal:2',
            'acknowledged' => 'boolean',
            'acknowledged_at' => 'datetime',
            'triggered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AssessmentTrend, $this>
     */
    public function trend(): BelongsTo
    {
        return $this->belongsTo(AssessmentTrend::class, 'assessment_trend_id');
    }

    /**
     * Set for element-level alerts, null for domain-level ones (DECISIONS D-36).
     *
     * @return BelongsTo<NcsbElement, $this>
     */
    public function ncsbElement(): BelongsTo
    {
        return $this->belongsTo(NcsbElement::class);
    }

    public function isForElement(): bool
    {
        return $this->ncsb_element_id !== null;
    }

    /**
     * What declined: the element code, or the domain name.
     */
    public function subjectLabel(): string
    {
        return $this->isForElement() ? $this->ncsbElement->code : $this->domain->label();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('acknowledged', false);
    }
}
