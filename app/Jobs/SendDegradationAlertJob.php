<?php

namespace App\Jobs;

use App\Mail\DegradationAlertMail;
use App\Models\AssessmentTrend;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Emails one Compliance Officer about the domains that declined in a new cycle (FR-19, DECISIONS D-34).
 */
class SendDegradationAlertJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 120, 240];

    public function __construct(public AssessmentTrend $trend, public User $officer) {}

    public function handle(): void
    {
        if (! $this->officer->is_active) {
            return;
        }

        Mail::to($this->officer)->send(new DegradationAlertMail($this->trend));
    }
}
