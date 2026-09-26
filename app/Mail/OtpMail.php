<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public int $ttlMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your NACSA Toolkit verification code');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.otp');
    }
}
