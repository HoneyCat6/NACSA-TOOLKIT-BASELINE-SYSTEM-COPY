<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Mail\DeadlineEscalationMail;
use App\Mail\DeadlineReminderMail;
use App\Models\DeadlineNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one recorded reminder or escalation and sets its delivery flag (FR-45, FR-46).
 */
class SendDeadlineNotificationJob implements ShouldQueue
{
    use Queueable;

    /**
     * FR-46: the first attempt plus 3 retries with backoff.
     */
    public int $tries = 4;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 120, 240];

    public bool $deleteWhenMissingModels = true;

    public function __construct(public DeadlineNotification $notification) {}

    public function handle(): void
    {
        $notification = $this->notification->loadMissing(['deadline', 'recipient']);

        if ($notification->delivered) {
            return;
        }

        if (! $notification->recipient->is_active) {
            $notification->update(['failure_reason' => 'The recipient account is deactivated.']);

            return;
        }

        Mail::to($notification->recipient)->send(match ($notification->type) {
            NotificationType::Reminder => new DeadlineReminderMail($notification),
            NotificationType::Escalation => new DeadlineEscalationMail($notification),
        });

        $notification->update(['delivered' => true, 'failure_reason' => null]);
    }

    /**
     * FR-46: still failing after the retries — keep `delivered = false` and say why. The dashboard surfaces it.
     */
    public function failed(?Throwable $exception): void
    {
        $this->notification->update([
            'delivered' => false,
            'failure_reason' => Str::limit($exception?->getMessage() ?: 'Delivery failed after retrying.', 500),
        ]);
    }
}
