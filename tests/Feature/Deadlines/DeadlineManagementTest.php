<?php

namespace Tests\Feature\Deadlines;

use App\Enums\DeadlineStatus;
use App\Enums\Role;
use App\Models\ComplianceDeadline;
use App\Models\DeadlineNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FR-40 deadline configuration, FR-43 page acknowledgement, D-40 manual "met", D-44 re-arming, D-47 contacts.
 */
class DeadlineManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00:00');
    }

    /**
     * @return array<string, array{Role, bool}>
     */
    public static function matrix(): array
    {
        return [
            'admin' => [Role::Admin, false],
            'compliance officer' => [Role::ComplianceOfficer, true],
            'executive reviewer' => [Role::ExecutiveReviewer, false],
        ];
    }

    /**
     * PRD §4.2: only Compliance Officers configure deadlines and see the notification history (SEC-03).
     */
    #[DataProvider('matrix')]
    public function test_role_permissions(Role $role, bool $allowed): void
    {
        $user = User::factory()->create(['role' => $role]);
        $deadline = ComplianceDeadline::factory()->create();

        $this->assertSame($allowed, $user->can('viewAny', ComplianceDeadline::class));
        $this->assertSame($allowed, $user->can('create', ComplianceDeadline::class));
        $this->assertSame($allowed, $user->can('update', $deadline));
        $this->assertSame($allowed, $user->can('acknowledge', $deadline));
        $this->assertSame($allowed, $user->can('markMet', $deadline));
        $this->assertSame($allowed, $user->can('delete', $deadline));
    }

    public function test_executive_reviewer_cannot_open_the_deadlines_page(): void
    {
        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get(route('deadlines.index'))
            ->assertForbidden();
    }

    public function test_officer_creates_a_deadline(): void
    {
        $officer = User::factory()->complianceOfficer()->create(['name' => 'Aina Officer']);
        $executive = User::factory()->executiveReviewer()->create();

        $this->signIn($officer)->post(route('deadlines.store'), [
            'title' => 'NCSB 2026 H2',
            'description' => 'Submit to the board risk committee.',
            'due_date' => '2026-12-31',
            'officer_id' => $officer->id,
            'secondary_contact_id' => $executive->id,
            'lead_days' => '7, 30,14',
        ])
            ->assertRedirect(route('deadlines.index'))
            ->assertSessionHas('status', '"NCSB 2026 H2" was added. Reminders go to Aina Officer 30, 14 and 7 days before 31 Dec 2026.');

        $deadline = ComplianceDeadline::query()->sole();
        $this->assertSame([30, 14, 7], $deadline->lead_days);
        $this->assertSame('2026-12-31', $deadline->due_date->toDateString());
        $this->assertSame($executive->id, $deadline->secondary_contact_id);
        $this->assertSame(DeadlineStatus::Upcoming, $deadline->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deadline.created',
            'user_id' => $officer->id,
            'auditable_id' => $deadline->id,
            'category' => 'compliance',
        ]);
    }

    public function test_the_create_form_defaults_to_the_current_officer_and_30_14_7(): void
    {
        $officer = User::factory()->complianceOfficer()->create();

        $this->signIn($officer)->get(route('deadlines.create'))
            ->assertOk()
            ->assertSee('value="30, 14, 7"', false)
            ->assertSee('<option value="'.$officer->id.'" selected>', false);
    }

    public function test_required_fields(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->post(route('deadlines.store'), [])
            ->assertSessionHasErrors([
                'title',
                'due_date',
                'officer_id',
                'secondary_contact_id' => 'Choose who is alerted if the deadline is missed.',
                'lead_days' => 'Give at least one reminder interval, e.g. 30, 14, 7.',
            ]);

        $this->assertDatabaseCount('compliance_deadlines', 0);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'past due date' => [['due_date' => '2026-09-30'], 'due_date', 'The due date cannot be in the past.'],
            'escalation contact is the primary contact' => [['secondary_contact_id' => 'officer'], 'secondary_contact_id', 'The escalation contact must be someone other than the primary contact.'],
            'escalation contact is an admin' => [['secondary_contact_id' => 'admin'], 'secondary_contact_id', 'Choose an active Compliance Officer or Executive Reviewer as the escalation contact.'],
            'escalation contact is deactivated' => [['secondary_contact_id' => 'inactive'], 'secondary_contact_id', 'Choose an active Compliance Officer or Executive Reviewer as the escalation contact.'],
            'primary contact is an executive' => [['officer_id' => 'executive'], 'officer_id', 'Choose an active Compliance Officer as the primary contact.'],
            'lead day is not a number' => [['lead_days' => '30, two weeks'], 'lead_days.1', 'Reminder intervals must be whole numbers of days, e.g. 30, 14, 7.'],
            'lead day repeated' => [['lead_days' => '14, 14'], 'lead_days.0', 'Each reminder interval can be listed once.'],
            'lead day out of range' => [['lead_days' => '400'], 'lead_days.0', 'Reminder intervals must be between 1 and 365 days.'],
            'too many lead days' => [['lead_days' => '60, 30, 14, 7, 3, 1'], 'lead_days', 'Give at most 5 reminder intervals.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_rejected(array $override, string $errorKey, string $message): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $users = [
            'officer' => $officer->id,
            'admin' => User::factory()->admin()->create()->id,
            'inactive' => User::factory()->executiveReviewer()->inactive()->create()->id,
            'executive' => User::factory()->executiveReviewer()->create()->id,
        ];
        $override = array_map(fn (mixed $value): mixed => $users[$value] ?? $value, $override);

        $this->signIn($officer)
            ->from(route('deadlines.create'))
            ->post(route('deadlines.store'), [...$this->validPayload($officer), ...$override])
            ->assertRedirect(route('deadlines.create'))
            ->assertSessionHasErrors([$errorKey => $message]);

        $this->assertDatabaseCount('compliance_deadlines', 0);
    }

    public function test_the_form_redisplays_rejected_lead_days(): void
    {
        $officer = User::factory()->complianceOfficer()->create();

        $this->signIn($officer)
            ->from(route('deadlines.create'))
            ->post(route('deadlines.store'), [...$this->validPayload($officer), 'lead_days' => '30, x'])
            ->assertRedirect(route('deadlines.create'));

        $this->signIn($officer)->get(route('deadlines.create'))->assertSee('value="30, x"', false);
    }

    /**
     * D-44: a new due date clears the acknowledgement so escalation is armed again; the edit is audited.
     */
    public function test_changing_the_due_date_rearms_and_clears_the_acknowledgement(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $deadline = ComplianceDeadline::factory()->dueIn(5)->acknowledged()->create(['officer_id' => $officer->id]);

        $this->signIn($officer)
            ->patch(route('deadlines.update', $deadline), [...$this->payloadFor($deadline), 'due_date' => '2026-11-30'])
            ->assertRedirect(route('deadlines.index'))
            ->assertSessionHas('status', "\"{$deadline->title}\" was updated. Reminders start over for the new due date, 30 Nov 2026.");

        $deadline->refresh();
        $this->assertNull($deadline->acknowledged_at);
        $this->assertNull($deadline->acknowledged_by);
        $this->assertSame(DeadlineStatus::Upcoming, $deadline->status);

        $metadata = json_decode(DB::table('audit_logs')->where('action', 'deadline.updated')->value('metadata'), true);
        $this->assertSame(['from' => '2026-10-06', 'to' => '2026-11-30'], $metadata['changes']['due_date']);
        $this->assertTrue($metadata['reminders_rearmed']);
    }

    public function test_other_edits_keep_the_acknowledgement(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $deadline = ComplianceDeadline::factory()->dueIn(5)->acknowledged()->create(['officer_id' => $officer->id]);

        $this->signIn($officer)
            ->patch(route('deadlines.update', $deadline), [...$this->payloadFor($deadline), 'title' => 'Renamed'])
            ->assertSessionHas('status', '"Renamed" was updated.');

        $this->assertNotNull($deadline->fresh()->acknowledged_at);
    }

    public function test_an_overdue_deadline_can_be_edited_without_moving_its_date(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $deadline = ComplianceDeadline::factory()->dueIn(-3)->create(['officer_id' => $officer->id]);

        $this->signIn($officer)
            ->patch(route('deadlines.update', $deadline), [...$this->payloadFor($deadline), 'description' => 'Board asked for an extension.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Board asked for an extension.', $deadline->fresh()->description);
    }

    public function test_saving_without_changes_writes_no_audit_row(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $deadline = ComplianceDeadline::factory()->create(['officer_id' => $officer->id]);

        $this->signIn($officer)
            ->patch(route('deadlines.update', $deadline), $this->payloadFor($deadline))
            ->assertSessionHas('status', "No changes to \"{$deadline->title}\".");

        $this->assertDatabaseMissing('audit_logs', ['action' => 'deadline.updated']);
    }

    public function test_officer_deletes_a_deadline_before_anything_was_sent(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $deadline = ComplianceDeadline::factory()->create();

        $this->signIn($officer)->delete(route('deadlines.destroy', $deadline))
            ->assertRedirect(route('deadlines.index'));

        $this->assertModelMissing($deadline);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deadline.deleted', 'auditable_id' => $deadline->id]);
    }

    /**
     * D-47: once a notification exists, deleting would erase FR-45 history.
     */
    public function test_a_deadline_with_notifications_cannot_be_deleted(): void
    {
        $deadline = ComplianceDeadline::factory()->create();
        DeadlineNotification::factory()->for($deadline, 'deadline')->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->delete(route('deadlines.destroy', $deadline))
            ->assertForbidden();

        $this->assertModelExists($deadline);
    }

    public function test_officer_acknowledges_from_the_deadlines_page(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $deadline = ComplianceDeadline::factory()->dueIn(5)->create();

        $this->signIn($officer)->from(route('deadlines.index'))
            ->post(route('deadlines.acknowledge', $deadline))
            ->assertRedirect(route('deadlines.index'))
            ->assertSessionHas('status', "\"{$deadline->title}\" was acknowledged. It will not be escalated; reminders continue until it is met.");

        $deadline->refresh();
        $this->assertSame($officer->id, $deadline->acknowledged_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deadline.acknowledged', 'user_id' => $officer->id]);
        $this->assertSame('deadlines page', json_decode(DB::table('audit_logs')->where('action', 'deadline.acknowledged')->value('metadata'), true)['via']);
    }

    public function test_acknowledging_twice_keeps_the_first_acknowledgement(): void
    {
        $deadline = ComplianceDeadline::factory()->acknowledged()->create();
        $firstAcknowledger = $deadline->acknowledged_by;

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->post(route('deadlines.acknowledge', $deadline))
            ->assertSessionHas('status', "\"{$deadline->title}\" was already acknowledged or met.");

        $this->assertSame($firstAcknowledger, $deadline->fresh()->acknowledged_by);
    }

    /**
     * D-40: the officer can mark any deadline met by hand, which stops reminders and escalation.
     */
    public function test_officer_marks_a_deadline_met(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $deadline = ComplianceDeadline::factory()->dueIn(-2)->create();

        $this->signIn($officer)->post(route('deadlines.met', $deadline))
            ->assertSessionHas('status', "\"{$deadline->title}\" was marked as met. No further reminders or escalation will be sent.");

        $deadline->refresh();
        $this->assertSame(DeadlineStatus::Met, $deadline->status);
        $this->assertNull($deadline->met_by_assessment_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deadline.met', 'user_id' => $officer->id, 'auditable_id' => $deadline->id]);
    }

    public function test_index_shows_open_deadlines_with_countdown_and_sent_reminders(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $overdue = ComplianceDeadline::factory()->dueIn(-2)->create(['title' => 'Overdue <b>cycle</b>']);
        $dueSoon = ComplianceDeadline::factory()->dueIn(10)->create(['title' => 'Upcoming cycle']);
        DeadlineNotification::factory()->for($dueSoon, 'deadline')->create(['lead_day' => 14]);
        ComplianceDeadline::factory()->met()->create(['title' => 'Last cycle']);

        $response = $this->signIn($officer)->get(route('deadlines.index'))->assertOk();

        $this->assertSame([$overdue->id, $dueSoon->id], $response->viewData('openDeadlines')->pluck('id')->all());
        $response->assertSeeText('2 days overdue')
            ->assertSee('Overdue &lt;b&gt;cycle&lt;/b&gt;', false)
            ->assertSeeText('10 days left')
            ->assertSeeText('14d · sent')
            ->assertSeeText('Last cycle')
            ->assertSeeText('Marked met by hand');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(User $officer): array
    {
        return [
            'title' => 'NCSB 2026 H2',
            'due_date' => '2026-12-31',
            'officer_id' => $officer->id,
            'secondary_contact_id' => User::factory()->executiveReviewer()->create()->id,
            'lead_days' => '30, 14, 7',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(ComplianceDeadline $deadline): array
    {
        return [
            'title' => $deadline->title,
            'description' => $deadline->description,
            'due_date' => $deadline->due_date->toDateString(),
            'officer_id' => $deadline->officer_id,
            'secondary_contact_id' => $deadline->secondary_contact_id,
            'lead_days' => implode(', ', $deadline->lead_days),
        ];
    }
}
