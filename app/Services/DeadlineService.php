<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\NotificationType;
use App\Jobs\SendDeadlineNotificationJob;
use App\Models\Assessment;
use App\Models\ComplianceDeadline;
use App\Models\DeadlineNotification;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * M3 Alert Service: reminder and escalation sweeps, acknowledgement and "met" (FR-41 – FR-44, DECISIONS D-40 – D-45).
 */
class DeadlineService
{
    public function __construct(private AuditService $audit) {}

    /**
     * FR-41: each open deadline gets one reminder for the interval it is currently in (D-43).
     * The unique index on (deadline, type, lead day, due date) makes it exactly-once, even if two sweeps race.
     *
     * @return int Reminders queued.
     */
    public function dispatchReminders(): int
    {
        $queued = 0;

        ComplianceDeadline::query()
            ->open()
            ->whereDate('due_date', '>=', today())
            ->lazyById()
            ->each(function (ComplianceDeadline $deadline) use (&$queued): void {
                $leadDay = $deadline->currentLeadDay();

                if ($leadDay !== null && $this->queue($deadline, NotificationType::Reminder, $leadDay, $deadline->officer_id)) {
                    $queued++;
                }
            });

        return $queued;
    }

    /**
     * FR-42: once the due date has passed with no upload ("met") and no acknowledgement, the secondary contact
     * is emailed once (D-42: escalations use lead day 0).
     *
     * @return int Escalations queued.
     */
    public function escalateOverdue(): int
    {
        $queued = 0;

        ComplianceDeadline::query()
            ->open()
            ->whereNull('acknowledged_at')
            ->whereNotNull('secondary_contact_id')
            ->whereDate('due_date', '<', today())
            ->lazyById()
            ->each(function (ComplianceDeadline $deadline) use (&$queued): void {
                if ($this->queue($deadline, NotificationType::Escalation, 0, $deadline->secondary_contact_id)) {
                    $queued++;
                }
            });

        return $queued;
    }

    /**
     * FR-44: re-derive the stored status after the date rolls over. The model re-derives it on every save.
     *
     * @return int Deadlines whose status changed.
     */
    public function refreshStatuses(): int
    {
        $changed = 0;

        ComplianceDeadline::query()
            ->open()
            ->lazyById()
            ->each(function (ComplianceDeadline $deadline) use (&$changed): void {
                if ($deadline->deriveStatus() !== $deadline->status) {
                    $deadline->save();
                    $changed++;
                }
            });

        return $changed;
    }

    /**
     * FR-43: acknowledgement means "I'm on it". It suppresses escalation; reminders keep coming (D-45).
     *
     * @param  string  $via  Where it came from, for the audit row: "email link" or "deadlines page".
     * @return bool False when the deadline was already acknowledged or met.
     */
    public function acknowledge(ComplianceDeadline $deadline, User $user, string $via): bool
    {
        if ($deadline->isAcknowledged() || $deadline->isMet()) {
            return false;
        }

        $deadline->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);

        $this->audit->log('deadline.acknowledged', AuditCategory::Compliance, $deadline, [
            'title' => $deadline->title,
            'due_date' => $deadline->due_date->toDateString(),
            'via' => $via,
        ], $user);

        return true;
    }

    /**
     * D-40: the officer can mark any deadline met by hand.
     */
    public function markMet(ComplianceDeadline $deadline, User $user): void
    {
        $deadline->update(['met_at' => now()]);

        $this->audit->log('deadline.met', AuditCategory::Compliance, $deadline, [
            'title' => $deadline->title,
            'due_date' => $deadline->due_date->toDateString(),
            'source' => 'manual',
        ], $user);
    }

    /**
     * D-40: a validated upload meets every open deadline that is not yet overdue.
     *
     * @return int Deadlines marked met.
     */
    public function markMetByUpload(Assessment $assessment, User $uploader): int
    {
        $deadlines = ComplianceDeadline::query()
            ->open()
            ->whereDate('due_date', '>=', today())
            ->get();

        foreach ($deadlines as $deadline) {
            $deadline->update([
                'met_at' => now(),
                'met_by_assessment_id' => $assessment->id,
            ]);

            $this->audit->log('deadline.met', AuditCategory::Compliance, $deadline, [
                'title' => $deadline->title,
                'due_date' => $deadline->due_date->toDateString(),
                'source' => 'upload',
                'cycle_label' => $assessment->cycle_label,
            ], $uploader);
        }

        return $deadlines->count();
    }

    /**
     * D-35 / D-46: deleting a cycle reopens the deadlines its upload met, so they can still be met or escalate.
     *
     * @return int Deadlines reopened.
     */
    public function reopenMetBy(Assessment $assessment, User $user): int
    {
        $deadlines = $assessment->metDeadlines()->get();

        foreach ($deadlines as $deadline) {
            $deadline->update([
                'met_at' => null,
                'met_by_assessment_id' => null,
            ]);

            $this->audit->log('deadline.reopened', AuditCategory::Compliance, $deadline, [
                'title' => $deadline->title,
                'due_date' => $deadline->due_date->toDateString(),
                'reason' => "Cycle {$assessment->cycle_label} was deleted",
            ], $user);
        }

        return $deadlines->count();
    }

    /**
     * Record the dispatch first, so the unique index decides whether this (deadline, interval, due date) was already sent.
     */
    private function queue(ComplianceDeadline $deadline, NotificationType $type, int $leadDay, int $recipientId): bool
    {
        try {
            $notification = DeadlineNotification::query()->create([
                'compliance_deadline_id' => $deadline->id,
                'type' => $type,
                'lead_day' => $leadDay,
                'due_date' => $deadline->due_date,
                'recipient_id' => $recipientId,
                'dispatched_at' => now(),
                'delivered' => false,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        SendDeadlineNotificationJob::dispatch($notification);

        return true;
    }
}
