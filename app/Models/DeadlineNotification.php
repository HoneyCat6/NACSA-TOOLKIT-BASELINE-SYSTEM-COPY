<?php

namespace App\Models;

use App\Enums\NotificationType;
use Database\Factories\DeadlineNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reminder or escalation email (FR-45). Escalations use `lead_day = 0` (DECISIONS D-42).
 */
#[Guarded(['id'])]
class DeadlineNotification extends Model
{
    /** @use HasFactory<DeadlineNotificationFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'due_date' => 'date',
            'dispatched_at' => 'datetime',
            'delivered' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ComplianceDeadline, $this>
     */
    public function deadline(): BelongsTo
    {
        return $this->belongsTo(ComplianceDeadline::class, 'compliance_deadline_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * FR-46: delivery gave up after its retries, or could not be attempted.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function failed(Builder $query): void
    {
        $query->where('delivered', false)->whereNotNull('failure_reason');
    }

    public function hasFailed(): bool
    {
        return ! $this->delivered && $this->failure_reason !== null;
    }

    /**
     * Delivered, Failed, or Pending while the queue worker is still trying.
     */
    public function deliveryLabel(): string
    {
        return match (true) {
            $this->delivered => 'Delivered',
            $this->hasFailed() => 'Failed',
            default => 'Pending',
        };
    }
}
