<?php

namespace App\Models;

use App\Enums\DeadlineStatus;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class ComplianceDeadline extends Model
{
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
     * @return BelongsTo<User, $this>
     */
    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'officer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function secondaryContact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secondary_contact_id');
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
}
