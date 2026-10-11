<?php

namespace App\Mail;

use App\Models\AssessmentTrend;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DegradationAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public AssessmentTrend $trend)
    {
        $this->trend->loadMissing(['assessment', 'previousAssessment', 'alerts']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Maturity decline detected in {$this->trend->assessment->cycle_label}");
    }

    public function content(): Content
    {
        return new Content(text: 'mail.degradation-alert');
    }
}
