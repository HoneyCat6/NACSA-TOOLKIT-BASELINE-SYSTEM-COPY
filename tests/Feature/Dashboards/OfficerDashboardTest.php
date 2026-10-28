<?php

namespace Tests\Feature\Dashboards;

use App\Models\Assessment;
use App\Models\AssessmentTrend;
use App\Models\ComplianceDeadline;
use App\Models\DeadlineNotification;
use App\Models\DegradationAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-71 Compliance Officer dashboard and its FR-73 first-run state (DECISIONS D-61 – D-62).
 */
class OfficerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_deadlines_are_listed_soonest_first_with_a_countdown_to_the_end_of_the_due_date(): void
    {
        $this->travelTo('2026-10-18 09:00:00');
        ComplianceDeadline::factory()->dueIn(20)->create(['title' => 'Cycle 2027 H1 due']);
        ComplianceDeadline::factory()->dueIn(3)->create(['title' => 'Cycle 2026 H2 due']);
        ComplianceDeadline::factory()->dueIn(5)->met()->create(['title' => 'Already met deadline']);

        $response = $this->signIn(User::factory()->complianceOfficer()->create())->get('/dashboard');

        $response->assertSeeTextInOrder(['Cycle 2026 H2 due', '3 days left', 'Cycle 2027 H1 due', '20 days left']);
        $response->assertSee("deadlineCountdown('2026-10-21T23:59:59+08:00')", false);
        $response->assertDontSeeText('Already met deadline');
    }

    public function test_overdue_deadlines_show_days_overdue_without_a_countdown(): void
    {
        $this->travelTo('2026-10-18 09:00:00');
        ComplianceDeadline::factory()->dueIn(-2)->create(['title' => 'Missed deadline']);

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/dashboard')
            ->assertSeeText('2 days overdue')
            ->assertSeeText('1 compliance deadline is overdue.')
            ->assertDontSee("deadlineCountdown('", false);
    }

    public function test_deadline_panel_invites_a_new_deadline_when_none_are_open(): void
    {
        ComplianceDeadline::factory()->met()->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/dashboard')
            ->assertSeeText('No open deadlines.')
            ->assertSee(route('deadlines.create'));
    }

    public function test_notification_summary_counts_the_last_30_days_by_type_and_delivery(): void
    {
        $this->travelTo('2026-10-18 09:00:00');
        $deadline = ComplianceDeadline::factory()->create(['title' => 'Cycle 2026 H2 due']);
        DeadlineNotification::factory()->for($deadline, 'deadline')->create(['lead_day' => 30, 'dispatched_at' => now()->subDays(29)]);
        DeadlineNotification::factory()->for($deadline, 'deadline')->create(['lead_day' => 14, 'dispatched_at' => now()->subDays(13)]);
        DeadlineNotification::factory()->for($deadline, 'deadline')->pending()->create(['lead_day' => 7, 'dispatched_at' => now()->subDays(6)]);
        DeadlineNotification::factory()->for($deadline, 'deadline')->escalation()->failed()->create(['dispatched_at' => now()->subDay()]);
        DeadlineNotification::factory()->count(4)->create(['dispatched_at' => now()->subDays(31)]);

        $response = $this->signIn(User::factory()->complianceOfficer()->create())->get('/dashboard');

        $response->assertViewHas('notificationSummary', [
            'reminders' => 3,
            'escalations' => 1,
            'delivered' => 2,
            'pending' => 1,
            'failed' => 1,
        ]);
        $response->assertSeeTextInOrder(['Escalation', 'Cycle 2026 H2 due', 'Failed']);
    }

    public function test_notification_summary_says_when_nothing_has_been_sent(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/dashboard')
            ->assertSeeText('No reminders or escalations sent yet.');
    }

    public function test_active_degradation_alerts_are_listed_with_an_acknowledge_button(): void
    {
        $active = DegradationAlert::factory()->create();
        $acknowledged = DegradationAlert::factory()->acknowledged()->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/dashboard')
            ->assertSeeText('PROTECT domain score fell by 0.50')
            ->assertSee(route('alerts.acknowledge', $active))
            ->assertDontSee(route('alerts.acknowledge', $acknowledged));
    }

    public function test_acknowledging_from_the_dashboard_returns_to_the_dashboard(): void
    {
        $alert = DegradationAlert::factory()->create();

        $response = $this->signIn(User::factory()->complianceOfficer()->create())
            ->from('/dashboard')
            ->post(route('alerts.acknowledge', $alert));

        $response->assertRedirect('/dashboard');
        $this->assertTrue($alert->refresh()->acknowledged);
    }

    public function test_each_domain_gets_its_own_trend_chart_with_its_latest_score_and_change(): void
    {
        $previous = Assessment::factory()->withDomainScores(['GOVERN' => 1.5, 'PROTECT' => 2.0])->create(['cycle_label' => 'Cycle 2025 H2', 'assessment_date' => '2025-10-01']);
        $latest = Assessment::factory()->withDomainScores(['GOVERN' => 2.25, 'PROTECT' => 1.5])->create(['cycle_label' => 'Cycle 2026 H1', 'assessment_date' => '2026-04-01']);
        AssessmentTrend::factory()->create([
            'assessment_id' => $latest->id,
            'prev_assessment_id' => $previous->id,
            'govern_delta' => 0.75,
            'protect_delta' => -0.5,
            'identify_delta' => 0,
        ]);

        $response = $this->signIn(User::factory()->complianceOfficer()->create())->get('/dashboard');

        foreach (['GV', 'ID', 'PR', 'DE', 'RS', 'RC'] as $prefix) {
            $response->assertSee('id="domain-trend-'.$prefix.'"', false);
        }
        $response->assertSeeTextInOrder(['Govern', '2.25', '▲ +0.75', 'improved', 'Identify', 'no change', 'Protect', '1.50', '▼ -0.50', 'declined']);
        $response->assertSee('"chart_id":"domain-trend-PR","data":[2,1.5],"declining":true', false);
        $response->assertSee('"chart_id":"domain-trend-GV","data":[1.5,2.25],"declining":false', false);
    }

    public function test_a_single_cycle_explains_that_change_needs_a_second_cycle(): void
    {
        Assessment::factory()->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/dashboard')
            ->assertSeeText('Needs a second cycle to compare');
    }

    public function test_first_run_dashboard_still_shows_deadlines_and_notifications_without_trend_charts(): void
    {
        ComplianceDeadline::factory()->dueIn(10)->create(['title' => 'First self-assessment due']);

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/dashboard')
            ->assertSeeText('No assessments yet')
            ->assertSee(route('assessments.upload'))
            ->assertSeeText('First self-assessment due')
            ->assertSeeText('Notification history')
            ->assertDontSeeText('Domain trends')
            ->assertDontSee('domain-trend-data', false);
    }
}
