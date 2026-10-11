<?php

namespace Tests\Feature\Assessments;

use App\Models\Assessment;
use App\Models\AssessmentTrend;
use App\Models\DegradationAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_an_empty_state_with_no_cycles(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/trends')
            ->assertOk()
            ->assertSeeText('No assessment cycles yet')
            ->assertSeeText('Upload the first assessment');
    }

    public function test_renders_the_chart_payload_and_flags_the_declining_domain(): void
    {
        $previous = Assessment::factory()->withDomainScores(['PROTECT' => 2.5])
            ->create(['cycle_label' => 'Cycle 2025 H2', 'assessment_date' => '2025-09-01']);
        $current = Assessment::factory()->withDomainScores(['PROTECT' => 2.0, 'GOVERN' => 2.5])
            ->create(['cycle_label' => 'Cycle 2026 H1', 'assessment_date' => '2026-03-01']);
        AssessmentTrend::factory()->create([
            'assessment_id' => $current->id,
            'prev_assessment_id' => $previous->id,
            'protect_delta' => -0.5,
            'govern_delta' => 0.5,
        ]);

        $response = $this->signIn(User::factory()->executiveReviewer()->create())->get('/trends')->assertOk();

        $chart = $response->viewData('chart');
        $this->assertSame(['Cycle 2025 H2', 'Cycle 2026 H1'], $chart['labels']);
        $this->assertTrue(collect($chart['datasets'])->firstWhere('label', 'PROTECT')['declining']);
        $this->assertSame(1, $response->viewData('improvingCount'));
        $this->assertSame(1, $response->viewData('decliningCount'));
        $response->assertSee('id="trend-data"', false);
    }

    public function test_officer_sees_an_acknowledge_button_for_active_alerts(): void
    {
        DegradationAlert::factory()->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/trends')
            ->assertOk()
            ->assertSeeText('PROTECT')
            ->assertSeeText('Acknowledge');
    }

    public function test_executive_reviewer_sees_alerts_without_an_acknowledge_button(): void
    {
        DegradationAlert::factory()->create();

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get('/trends')
            ->assertOk()
            ->assertSeeText('PROTECT')
            ->assertDontSeeText('Acknowledge');
    }
}
