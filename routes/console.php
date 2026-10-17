<?php

use App\Jobs\DispatchDeadlineRemindersJob;
use App\Jobs\EscalateOverdueDeadlinesJob;
use App\Services\DeadlineService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * M3 deadline sweeps (FR-41, FR-42, TECH_SPEC §8.1). Needs `php artisan schedule:work` and a queue worker.
 */
Schedule::job(new DispatchDeadlineRemindersJob)->dailyAt('08:00')
    ->name('deadline-reminders')->withoutOverlapping();

Schedule::job(new EscalateOverdueDeadlinesJob)->dailyAt('08:15')
    ->name('deadline-escalations')->withoutOverlapping();

Schedule::command('deadlines:refresh-status')->hourly();

Artisan::command('deadlines:refresh-status', function (DeadlineService $deadlines) {
    $this->info($deadlines->refreshStatuses().' deadline status(es) updated.');
})->purpose('Re-derive each open deadline\'s status from today\'s date (FR-44)');

/*
 * TECH_SPEC §12.4: run both sweeps as if today were --days from now, sending mail straight away.
 * Notifications it records count as sent, so the real sweep will not repeat them on that day.
 */
Artisan::command('deadlines:simulate-sweep {--days=0 : Days from today to pretend it is}', function (DeadlineService $deadlines) {
    if (! app()->environment('local')) {
        $this->error('deadlines:simulate-sweep only runs in the local environment.');

        return 1;
    }

    config(['queue.default' => 'sync']);
    Carbon::setTestNow(now()->addDays((int) $this->option('days')));

    try {
        $this->info('Running the deadline sweep as if today were '.today()->toDateString().'.');
        $deadlines->refreshStatuses();
        $this->info($deadlines->dispatchReminders().' reminder(s) and '.$deadlines->escalateOverdue().' escalation(s) sent.');
    } finally {
        Carbon::setTestNow();
    }

    return 0;
})->purpose('Dev only: fire the deadline sweep at an arbitrary day offset');
