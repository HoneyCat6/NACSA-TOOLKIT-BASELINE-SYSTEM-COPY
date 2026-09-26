<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class DeadlineNotification extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
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
}
