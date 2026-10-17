<?php

namespace App\Mail;

use App\Models\DeadlineNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * FR-41 reminder to the primary contact, with the signed acknowledgement link (FR-43).
 */
class DeadlineReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DeadlineNotification $notification)
    {
        $this->notification->loadMissing(['deadline.secondaryContact', 'recipient']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Reminder: {$this->notification->deadline->title} is due {$this->dueIn()}");
    }

    public function content(): Content
    {
        $deadline = $this->notification->deadline;

        return new Content(text: 'mail.deadline-reminder', with: [
            'deadline' => $deadline,
            'dueIn' => $this->dueIn(),
            'acknowledgeUrl' => $deadline->isAcknowledged() ? null : URL::temporarySignedRoute(
                'deadlines.acknowledge-link',
                $deadline->due_date->copy()->endOfDay(),
                ['deadline' => $deadline, 'user' => $this->notification->recipient, 'due' => $deadline->due_date->toDateString()],
            ),
        ]);
    }

    private function dueIn(): string
    {
        $days = $this->notification->deadline->daysRemaining();

        return match ($days) {
            0 => 'today',
            1 => 'tomorrow',
            default => "in {$days} days",
        };
    }
}
