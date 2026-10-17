<?php

namespace App\Jobs;

use App\Services\DeadlineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily reminder sweep (FR-41, TECH_SPEC §8.1).
 */
class DispatchDeadlineRemindersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function handle(DeadlineService $deadlines): void
    {
        $deadlines->refreshStatuses();
        $deadlines->dispatchReminders();
    }
}
