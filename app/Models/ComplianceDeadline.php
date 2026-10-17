<?php

namespace App\Models;

use App\Enums\DeadlineStatus;
use App\Enums\NotificationType;
use Carbon\CarbonInterface;
use Database\Factories\ComplianceDeadlineFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class ComplianceDeadline extends Model
{
    /** @use HasFactory<ComplianceDeadlineFactory> */
    use HasFactory;

    /**
     * Default lead-time intervals in days (FR-40).
     *
     * @var list<int>
     */
    public const DefaultLeadDays = [30, 14, 7];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lead_days' => '[30,14,7]',
        'status' => 'upcoming',
    ];

    /**
     * FR-44: the stored status is never set by hand. It is re-derived on every save and by `deadlines:refresh-status`.
     */
    protected static function booted(): void
    {
        static::saving(function (self $deadline): void {
            $deadline->status = $deadline->deriveStatus();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'lead_days' => 'array',
            'status' => DeadlineStatus::class,
            'acknowledged_at' => 'datetime',
            'met_at' => 'datetime',
        ];
    }

    /**
     * The primary contact, who receives the reminders.
     *
     * @return BelongsTo<User, $this>
     */
    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    /**
     * The escalation contact (FR-42).
     *
     * @return BelongsTo<User, $this>
     */
    public function secondaryContact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secondary_contact_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function metByAssessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'met_by_assessment_id');
    }

    /**
     * @return HasMany<DeadlineNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(DeadlineNotification::class);
    }

    /**
     * Deadlines not yet met, whether or not they are overdue.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->whereNull('met_at');
    }

    public function isMet(): bool
    {
        return $this->met_at !== null;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    /**
     * Whole days from today to the due date; negative once it has passed.
     */
    public function daysRemaining(?CarbonInterface $today = null): int
    {
        return (int) ($today ?? now())->copy()->startOfDay()->diffInDays($this->due_date->copy()->startOfDay(), false);
    }

    /**
     * The configured intervals, largest first.
     *
     * @return list<int>
     */
    public function leadDays(): array
    {
        $leadDays = array_map('intval', $this->lead_days ?: self::DefaultLeadDays);
        rsort($leadDays);

        return $leadDays;
    }

    /**
     * Lead days whose reminder has gone out for the current due date (D-44: an earlier due date's reminders don't count).
     *
     * @return list<int>
     */
    public function sentReminderLeadDays(): array
    {
        return $this->notificationsForCurrentDueDate()
            ->where('type', NotificationType::Reminder)
            ->pluck('lead_day')
            ->all();
    }

    public function wasEscalated(): bool
    {
        return $this->notificationsForCurrentDueDate()->contains('type', NotificationType::Escalation);
    }

    /**
     * DECISIONS D-41: Met if met; else Overdue once the due date has passed; else Due Soon within the smallest lead day;
     * else Upcoming.
     */
    public function deriveStatus(?CarbonInterface $today = null): DeadlineStatus
    {
        if ($this->isMet()) {
            return DeadlineStatus::Met;
        }

        $daysRemaining = $this->daysRemaining($today);

        return match (true) {
            $daysRemaining < 0 => DeadlineStatus::Overdue,
            $daysRemaining <= min($this->leadDays()) => DeadlineStatus::DueSoon,
            default => DeadlineStatus::Upcoming,
        };
    }

    /**
     * DECISIONS D-43: the interval the deadline is currently inside — the smallest lead day already reached.
     * A sweep that was missed, or a deadline created late, sends this one reminder rather than every skipped interval.
     */
    public function currentLeadDay(?CarbonInterface $today = null): ?int
    {
        $daysRemaining = $this->daysRemaining($today);

        if ($this->isMet() || $daysRemaining < 0) {
            return null;
        }

        $reached = array_filter($this->leadDays(), fn (int $leadDay): bool => $leadDay >= $daysRemaining);

        return $reached === [] ? null : min($reached);
    }

    /**
     * @return Collection<int, DeadlineNotification>
     */
    private function notificationsForCurrentDueDate(): Collection
    {
        return $this->notifications->filter(
            fn (DeadlineNotification $notification): bool => $notification->due_date->isSameDay($this->due_date),
        );
    }
}
