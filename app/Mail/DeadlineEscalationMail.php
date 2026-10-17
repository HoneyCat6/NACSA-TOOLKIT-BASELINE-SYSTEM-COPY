<?php

namespace App\Mail;

use App\Enums\Role;
use App\Models\DeadlineNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * FR-42 overdue alert to the secondary (escalation) contact.
 */
class DeadlineEscalationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DeadlineNotification $notification)
    {
        $this->notification->loadMissing(['deadline.officer', 'recipient']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Overdue: {$this->notification->deadline->title} was due on {$this->notification->deadline->due_date->format('j M Y')}");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.deadline-escalation', with: [
            'deadline' => $this->notification->deadline,
            'daysOverdue' => -$this->notification->deadline->daysRemaining(),
            // Executive Reviewers cannot open the deadlines page (PRD §4.2), so they get the sign-in link instead.
            'link' => $this->notification->recipient->hasRole(Role::ComplianceOfficer)
                ? route('deadlines.index')
                : route('dashboard'),
        ]);
    }
}
