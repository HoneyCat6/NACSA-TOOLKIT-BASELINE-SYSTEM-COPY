<?php

namespace Tests\Feature\Deadlines;

use App\Enums\DeadlineStatus;
use App\Enums\NotificationType;
use App\Jobs\DispatchDeadlineRemindersJob;
use App\Jobs\EscalateOverdueDeadlinesJob;
use App\Mail\DeadlineEscalationMail;
use App\Mail\DeadlineReminderMail;
use App\Models\ComplianceDeadline;
use App\Models\DeadlineNotification;
use App\Models\User;
use App\Services\DeadlineService;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * M3 reminder and escalation sweeps (FR-41, FR-42, FR-44, FR-46, DECISIONS D-41 – D-45).
 */
class DeadlineSweepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 08:00:00');
    }

    /**
     * @return array<string, array{int, list<int>, DeadlineStatus}>
     */
    public static function statuses(): array
    {
        return [
            'beyond the smallest lead day' => [8, [30, 14, 7], DeadlineStatus::Upcoming],
            'on the smallest lead day' => [7, [30, 14, 7], DeadlineStatus::DueSoon],
            'due today' => [0, [30, 14, 7], DeadlineStatus::DueSoon],
            'one day past' => [-1, [30, 14, 7], DeadlineStatus::Overdue],
            'custom smallest lead day' => [5, [10, 5], DeadlineStatus::DueSoon],
            'just outside a custom lead day' => [6, [10, 5], DeadlineStatus::Upcoming],
        ];
    }

    /**
     * FR-44, D-41: status is derived from the date and stored on save.
     *
     * @param  list<int>  $leadDays
     */
    #[DataProvider('statuses')]
    public function test_status_is_derived_from_the_due_date(int $dueInDays, array $leadDays, DeadlineStatus $expected): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn($dueInDays)->create(['lead_days' => $leadDays]);

        $this->assertSame($expected, $deadline->deriveStatus());
        $this->assertSame($expected, $deadline->fresh()->status);
    }

    public function test_a_met_deadline_is_met_even_when_overdue(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(-3)->met()->create();

        $this->assertSame(DeadlineStatus::Met, $deadline->fresh()->status);
    }

    public function test_refresh_updates_stored_status_when_the_date_rolls_over(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(8)->create();
        $this->assertSame(DeadlineStatus::Upcoming, $deadline->status);

        $this->travel(1)->day();
        $this->artisan('deadlines:refresh-status')->expectsOutput('1 deadline status(es) updated.')->assertSuccessful();

        $this->assertSame(DeadlineStatus::DueSoon, $deadline->fresh()->status);
    }

    /**
     * FR-41: a second sweep on the same day sends nothing new.
     */
    public function test_reminder_fires_exactly_once_per_interval(): void
    {
        Mail::fake();
        $deadline = ComplianceDeadline::factory()->dueIn(30)->create();
        $sweep = app(DeadlineService::class);

        $this->assertSame(1, $sweep->dispatchReminders());
        $this->assertSame(0, $sweep->dispatchReminders());

        $notification = DeadlineNotification::query()->sole();
        $this->assertSame(NotificationType::Reminder, $notification->type);
        $this->assertSame(30, $notification->lead_day);
        $this->assertSame($deadline->officer_id, $notification->recipient_id);
        $this->assertTrue($notification->delivered);
        Mail::assertSent(DeadlineReminderMail::class, 1);
        Mail::assertSent(DeadlineReminderMail::class, fn (DeadlineReminderMail $mail) => $mail->hasTo($deadline->officer->email));
    }

    public function test_each_interval_fires_as_the_due_date_approaches(): void
    {
        Mail::fake();
        $deadline = ComplianceDeadline::factory()->dueIn(30)->create();
        $sweep = app(DeadlineService::class);

        foreach (range(30, 0) as $daysRemaining) {
            $sweep->dispatchReminders();
            $this->travel(1)->day();
        }

        $this->assertSame([30, 14, 7], $deadline->notifications()->orderBy('id')->pluck('lead_day')->all());
        Mail::assertSent(DeadlineReminderMail::class, 3);
    }

    /**
     * D-43: a deadline created inside several intervals gets one reminder, for the interval it is in now.
     */
    public function test_a_late_deadline_gets_only_the_current_interval(): void
    {
        Mail::fake();
        $deadline = ComplianceDeadline::factory()->dueIn(10)->create();

        app(DeadlineService::class)->dispatchReminders();

        $this->assertSame([14], $deadline->notifications()->pluck('lead_day')->all());
    }

    public function test_reminder_subject_says_when_it_is_due(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(14)->create(['title' => 'NCSB 2026 H2 & audit']);
        $notification = DeadlineNotification::factory()->for($deadline, 'deadline')->create(['lead_day' => 14]);

        $mail = new DeadlineReminderMail($notification);

        $mail->assertHasSubject('Reminder: NCSB 2026 H2 & audit is due in 14 days');
        // Plain-text mail must not HTML-escape the title or the signed link.
        $mail->assertSeeInText('"NCSB 2026 H2 & audit"');
        $mail->assertDontSeeInText('&amp;');
        $mail->assertSeeInText('due in 14 days, on Thursday, 15 Oct 2026');
        $mail->assertSeeInText(route('deadlines.index'));
    }

    public function test_no_reminders_for_met_deadlines(): void
    {
        Mail::fake();
        ComplianceDeadline::factory()->dueIn(7)->met()->create();

        $this->assertSame(0, app(DeadlineService::class)->dispatchReminders());

        Mail::assertNothingSent();
    }

    /**
     * D-45: acknowledging suppresses escalation only; reminders keep coming.
     */
    public function test_acknowledged_deadlines_still_get_reminders_without_the_link(): void
    {
        Mail::fake();
        ComplianceDeadline::factory()->dueIn(7)->acknowledged()->create();

        $this->assertSame(1, app(DeadlineService::class)->dispatchReminders());

        Mail::assertSent(DeadlineReminderMail::class, function (DeadlineReminderMail $mail): bool {
            $mail->assertDontSeeInText('/acknowledge/');
            $mail->assertSeeInText('so it will not be escalated');

            return true;
        });
    }

    /**
     * FR-42, D-42: the escalation goes to the secondary contact once, stored with lead day 0.
     */
    public function test_overdue_unacknowledged_deadline_escalates_once_to_the_secondary_contact(): void
    {
        Mail::fake();
        $deadline = ComplianceDeadline::factory()->dueIn(-1)->create(['title' => 'NCSB 2026 H2']);
        $sweep = app(DeadlineService::class);

        $this->assertSame(1, $sweep->escalateOverdue());
        $this->assertSame(0, $sweep->escalateOverdue());

        $notification = DeadlineNotification::query()->sole();
        $this->assertSame(NotificationType::Escalation, $notification->type);
        $this->assertSame(0, $notification->lead_day);
        $this->assertSame($deadline->secondary_contact_id, $notification->recipient_id);
        Mail::assertSent(DeadlineEscalationMail::class, 1);
        Mail::assertSent(DeadlineEscalationMail::class, function (DeadlineEscalationMail $mail) use ($deadline): bool {
            $mail->assertHasSubject('Overdue: NCSB 2026 H2 was due on 30 Sep 2026');
            $mail->assertSeeInText($deadline->officer->email);
            // The secondary contact is an Executive Reviewer, who cannot open the deadlines page.
            $mail->assertDontSeeInText(route('deadlines.index'));

            return $mail->hasTo($deadline->secondaryContact->email);
        });
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notEscalated(): array
    {
        return [
            'due today' => ['dueToday'],
            'acknowledged' => ['acknowledged'],
            'met' => ['met'],
        ];
    }

    #[DataProvider('notEscalated')]
    public function test_deadlines_that_are_not_escalated(string $case): void
    {
        Mail::fake();
        match ($case) {
            'dueToday' => ComplianceDeadline::factory()->dueIn(0)->create(),
            'acknowledged' => ComplianceDeadline::factory()->dueIn(-1)->acknowledged()->create(),
            'met' => ComplianceDeadline::factory()->dueIn(-1)->met()->create(),
        };

        $this->assertSame(0, app(DeadlineService::class)->escalateOverdue());

        Mail::assertNothingSent();
    }

    /**
     * D-44: reminders for the old due date stay in the history; the new due date gets its own.
     */
    public function test_a_new_due_date_rearms_reminders_and_escalation(): void
    {
        Mail::fake();
        $deadline = ComplianceDeadline::factory()->dueIn(-1)->create();
        DeadlineNotification::factory()->for($deadline, 'deadline')->create(['lead_day' => 14]);
        $sweep = app(DeadlineService::class);
        $sweep->escalateOverdue();

        $deadline->update(['due_date' => today()->addDays(7)]);
        $this->assertSame(1, $sweep->dispatchReminders());

        $this->travel(8)->days();
        $this->assertSame(1, $sweep->escalateOverdue());

        $this->assertSame(4, $deadline->notifications()->count());
        $this->assertSame([7], $deadline->fresh()->load('notifications')->sentReminderLeadDays());
    }

    /**
     * FR-46: a send that still fails after its retries is kept with `delivered = false` and the reason.
     */
    public function test_failed_delivery_is_recorded(): void
    {
        $deadline = ComplianceDeadline::factory()->dueIn(30)->create();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused by smtp.example.com'));

        try {
            app(DeadlineService::class)->dispatchReminders();
            $this->fail('The sync queue should rethrow the mail failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Connection refused by smtp.example.com', $exception->getMessage());
        }

        $notification = $deadline->notifications()->sole();
        $this->assertFalse($notification->delivered);
        $this->assertSame('Connection refused by smtp.example.com', $notification->failure_reason);
        $this->assertTrue($notification->hasFailed());
    }

    public function test_a_deactivated_recipient_is_recorded_as_a_failure_without_sending(): void
    {
        Mail::fake();
        $deadline = ComplianceDeadline::factory()->dueIn(30)->create([
            'officer_id' => User::factory()->complianceOfficer()->inactive(),
        ]);

        app(DeadlineService::class)->dispatchReminders();

        Mail::assertNothingSent();
        $this->assertSame('The recipient account is deactivated.', $deadline->notifications()->sole()->failure_reason);
    }

    public function test_sweep_jobs_run_both_sweeps(): void
    {
        Mail::fake();
        ComplianceDeadline::factory()->dueIn(14)->create();
        ComplianceDeadline::factory()->dueIn(-2)->create();

        DispatchDeadlineRemindersJob::dispatch();
        EscalateOverdueDeadlinesJob::dispatch();

        Mail::assertSent(DeadlineReminderMail::class, 1);
        Mail::assertSent(DeadlineEscalationMail::class, 1);
    }

    /**
     * TECH_SPEC §8.1: the sweeps run daily at 08:00 and 08:15.
     */
    public function test_sweeps_are_scheduled_daily(): void
    {
        $this->app->make(Kernel::class)->bootstrap();

        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => $event instanceof CallbackEvent)
            ->mapWithKeys(fn (CallbackEvent $event): array => [$event->description => $event->expression]);

        $this->assertSame('0 8 * * *', $events['deadline-reminders']);
        $this->assertSame('15 8 * * *', $events['deadline-escalations']);
    }
}
