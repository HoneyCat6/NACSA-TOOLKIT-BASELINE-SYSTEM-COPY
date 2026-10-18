<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * System health indicators for the Admin dashboard (FR-70): queue depth, failed jobs and the scheduler heartbeat.
 */
class SystemHealthService
{
    public const HeartbeatCacheKey = 'scheduler:heartbeat';

    /**
     * The scheduler runs every minute (TECH_SPEC §12.3), so a heartbeat older than this means it has stopped.
     */
    public const HeartbeatStaleAfterMinutes = 5;

    /**
     * Called by the every-minute `scheduler-heartbeat` task in `routes/console.php`.
     */
    public function recordSchedulerHeartbeat(): void
    {
        Cache::forever(self::HeartbeatCacheKey, now()->getTimestamp());
    }

    public function lastSchedulerRun(): ?CarbonImmutable
    {
        $timestamp = Cache::get(self::HeartbeatCacheKey);

        return $timestamp === null ? null : CarbonImmutable::createFromTimestamp($timestamp, config('app.timezone'));
    }

    public function isSchedulerRunning(): bool
    {
        $lastRun = $this->lastSchedulerRun();

        return $lastRun !== null && $lastRun->greaterThanOrEqualTo(now()->subMinutes(self::HeartbeatStaleAfterMinutes));
    }

    /**
     * Jobs waiting for the queue worker.
     */
    public function queuedJobCount(): int
    {
        return DB::table('jobs')->count();
    }

    /**
     * Jobs that gave up after retrying.
     */
    public function failedJobCount(): int
    {
        return DB::table('failed_jobs')->count();
    }
}
