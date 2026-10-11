<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\DegradationAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_dashboard_invites_the_first_upload_when_empty(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('No assessments yet')
            ->assertSee(route('assessments.upload'));
    }

    public function test_officer_dashboard_shows_the_latest_cycle_and_active_alerts(): void
    {
        Assessment::factory()->create(['cycle_label' => 'Cycle 2025 H2', 'assessment_date' => '2025-09-01']);
        Assessment::factory()->create(['cycle_label' => 'Cycle 2026 H1', 'assessment_date' => today()]);
        DegradationAlert::factory()->count(2)->create();
        DegradationAlert::factory()->acknowledged()->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Cycle 2026 H1')
            ->assertDontSeeText('No assessments yet')
            ->assertSeeText('2 active degradation alerts need acknowledgement.');
    }

    public function test_executive_dashboard_shows_the_latest_cycle_without_a_results_link(): void
    {
        $assessment = Assessment::factory()->create(['cycle_label' => 'Cycle 2026 H1']);

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeText('Cycle 2026 H1')
            ->assertSee(route('trends.index'))
            ->assertDontSee(route('assessments.show', $assessment))
            ->assertDontSee(route('assessments.index'));
    }
}
