<?php

namespace Tests\Feature\Dashboards;

use App\Enums\Band;
use App\Models\Assessment;
use App\Models\AssessmentTrend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-72 Executive Reviewer dashboard and its FR-73 first-run state (DECISIONS D-63).
 */
class ExecutiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_posture_is_non_compliant_when_any_area_is_critical(): void
    {
        Assessment::factory()->withDomainScores(['GOVERN' => 0.5])->create(['cycle_label' => 'Cycle 2026 H1', 'overall_band' => Band::Intermediate]);

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get('/dashboard')
            ->assertSeeTextInOrder(['Overall compliance posture', 'Cycle 2026 H1', 'Non-compliant', 'Critical gaps remain', 'Maturity band', 'Intermediate'])
            ->assertSeeText('not a legal determination');
    }

    public function test_posture_is_compliant_when_advanced_with_every_area_tolerable_and_nothing_unanswered(): void
    {
        Assessment::factory()
            ->withDomainScores(['GOVERN' => 2.5, 'IDENTIFY' => 2.5, 'PROTECT' => 1.75, 'DETECT' => 3.0, 'RESPOND' => 2.5, 'RECOVER' => 2.0])
            ->create(['overall_score' => 0.97, 'overall_band' => Band::Advanced, 'unanswered_count' => 0]);

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get('/dashboard')
            ->assertSeeText('Compliant')
            ->assertDontSeeText('Partially compliant')
            ->assertDontSeeText('Non-compliant');
    }

    public function test_risk_by_area_uses_plain_language_names_with_category_change_and_narrative(): void
    {
        $previous = Assessment::factory()->create(['assessment_date' => '2025-10-01']);
        $latest = Assessment::factory()
            ->withDomainScores(['GOVERN' => 0.5, 'IDENTIFY' => 1.25, 'PROTECT' => 2.0, 'DETECT' => 2.75, 'RESPOND' => null, 'RECOVER' => 2.0])
            ->create(['assessment_date' => '2026-04-01']);
        AssessmentTrend::factory()->create([
            'assessment_id' => $latest->id,
            'prev_assessment_id' => $previous->id,
            'govern_delta' => -1.5,
            'detect_delta' => 0.75,
            'recover_delta' => 0,
        ]);

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get('/dashboard')
            ->assertSeeTextInOrder([
                'Cybersecurity governance', 'NCSB GOVERN', 'Critical', '▼ Declined', 'Controls in cybersecurity governance are largely absent.',
                'Asset and risk identification', 'High',
                'Protective security', 'Moderate',
                'Threat detection', 'Acceptable', '▲ Improved',
                'Incident response', 'Not assessed', 'cannot be judged',
                'Recovery and continuity', 'Moderate', 'No change',
            ]);
    }

    public function test_dashboard_offers_heatmap_and_simulator_shortcuts_but_no_cycle_results(): void
    {
        $assessment = Assessment::factory()->create();

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get('/dashboard')
            ->assertSee(route('heatmap.index'))
            ->assertSee(route('simulator.index'))
            ->assertDontSee(route('assessments.show', $assessment));
    }

    public function test_first_run_dashboard_offers_to_email_the_active_compliance_officers(): void
    {
        User::factory()->complianceOfficer()->create(['name' => 'Aina', 'email' => 'aina@example.test']);
        User::factory()->complianceOfficer()->create(['name' => 'Badrul', 'email' => 'badrul@example.test']);
        User::factory()->complianceOfficer()->inactive()->create(['email' => 'former@example.test']);

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get('/dashboard')
            ->assertSeeText('No assessment results yet')
            ->assertSee('href="mailto:aina@example.test,badrul@example.test?subject=NCSB%20self-assessment%3A%20first%20upload"', false)
            ->assertSeeText('Email the Compliance Officers')
            ->assertDontSee('former@example.test')
            ->assertDontSee(route('heatmap.index').'" class="card', false);
    }

    public function test_first_run_dashboard_points_to_the_administrator_when_no_officer_is_active(): void
    {
        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get('/dashboard')
            ->assertSeeText('Ask your system administrator to create one.')
            ->assertDontSee('mailto:', false);
    }
}
