<?php

namespace App\Jobs;

use App\Services\DeadlineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily escalation sweep (FR-42, TECH_SPEC §8.1).
 */
class EscalateOverdueDeadlinesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(DeadlineService $deadlines): void
    {
        $deadlines->refreshStatuses();
        $deadlines->escalateOverdue();
    }
}
