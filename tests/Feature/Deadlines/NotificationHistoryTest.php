<?php

namespace Tests\Feature\Deadlines;

use App\Enums\AuditCategory;
use App\Models\AuditLog;
use App\Models\ComplianceDeadline;
use App\Models\DeadlineNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-45 notification history, FR-46 failures on the dashboard, and the officer's compliance audit trail (D-54).
 */
class NotificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_sees_every_dispatch_with_its_delivery_state(): void
    {
        $deadline = ComplianceDeadline::factory()->create(['title' => 'NCSB 2026 H2']);
        DeadlineNotification::factory()->for($deadline, 'deadline')->create(['lead_day' => 30]);
        DeadlineNotification::factory()->for($deadline, 'deadline')->failed('SMTP timeout')->create(['lead_day' => 14]);
        DeadlineNotification::factory()->for($deadline, 'deadline')->escalation()->pending()->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSeeText('NCSB 2026 H2')
            ->assertSeeText($deadline->officer->email)
            ->assertSeeText($deadline->secondaryContact->email)
            ->assertSeeText('30 days')
            ->assertSeeText('Overdue')
            ->assertSeeText('Delivered')
            ->assertSeeText('SMTP timeout')
            ->assertSeeText('Pending');
    }

    public function test_filters_by_delivery_and_type(): void
    {
        $deadline = ComplianceDeadline::factory()->create();
        DeadlineNotification::factory()->for($deadline, 'deadline')->create(['lead_day' => 30]);
        $failed = DeadlineNotification::factory()->for($deadline, 'deadline')->failed()->create(['lead_day' => 14]);
        $escalation = DeadlineNotification::factory()->for($deadline, 'deadline')->escalation()->create();
        $officer = User::factory()->complianceOfficer()->create();

        $failedOnly = $this->signIn($officer)->get(route('notifications.index', ['delivery' => 'failed']))->viewData('notifications');
        $escalationsOnly = $this->signIn($officer)->get(route('notifications.index', ['type' => 'escalation']))->viewData('notifications');

        $this->assertSame([$failed->id], $failedOnly->pluck('id')->all());
        $this->assertSame([$escalation->id], $escalationsOnly->pluck('id')->all());
    }

    public function test_executive_reviewer_cannot_see_the_history(): void
    {
        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get(route('notifications.index'))
            ->assertForbidden();
    }

    /**
     * FR-46: undelivered emails surface on the officer's dashboard, along with overdue deadlines.
     */
    public function test_dashboard_surfaces_failed_emails_and_overdue_deadlines(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $overdue = ComplianceDeadline::factory()->dueIn(-1)->create();
        DeadlineNotification::factory()->for($overdue, 'deadline')->failed()->count(2)->sequence(['lead_day' => 14], ['lead_day' => 7])->create();

        $this->signIn($officer)->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('1 compliance deadline is overdue.')
            ->assertSeeText('2 deadline emails could not be delivered after retrying.');
    }

    public function test_dashboard_is_quiet_when_nothing_is_wrong(): void
    {
        ComplianceDeadline::factory()->dueIn(10)->create();

        $this->signIn(User::factory()->complianceOfficer()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('overdue')
            ->assertDontSeeText('could not be delivered');
    }

    public function test_officer_sees_the_compliance_trail_but_not_technical_entries(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        AuditLog::query()->create(['action' => 'deadline.created', 'category' => AuditCategory::Compliance, 'user_id' => $officer->id, 'created_at' => now()]);
        AuditLog::query()->create(['action' => 'login.failed', 'category' => AuditCategory::Technical, 'created_at' => now()]);

        $response = $this->signIn($officer)->get(route('audit-trail.index', ['action' => 'deadline.created']))->assertOk();

        $this->assertSame(['deadline.created'], $response->viewData('logs')->pluck('action')->all());
        $this->assertSame(['deadline.created'], $response->viewData('actions')->all());
        $response->assertDontSeeText('login.failed');
    }

    public function test_admin_cannot_open_the_compliance_trail(): void
    {
        $this->signIn(User::factory()->admin()->create())
            ->get(route('audit-trail.index'))
            ->assertForbidden();
    }
}
