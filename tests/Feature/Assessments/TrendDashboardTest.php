<?php

namespace Tests\Feature\Assessments;

use App\Models\Assessment;
use App\Models\AssessmentTrend;
use App\Models\DegradationAlert;
use App\Models\User;
use Database\Seeders\DemoAssessmentSeeder;
use Database\Seeders\NcsbElementSeeder;
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

    /**
     * Proposal Table 5.2 "trend coverage completeness": all 33 elements appear with a level for every cycle (DECISIONS D-36).
     */
    public function test_element_history_covers_all_33_elements_in_every_cycle(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $this->seed([NcsbElementSeeder::class, DemoAssessmentSeeder::class]);

        $response = $this->signIn($officer)->get('/trends')->assertOk();

        $history = collect($response->viewData('elementHistory'));
        $this->assertCount(33, $history);
        $this->assertTrue($history->every(fn (array $element): bool => count($element['levels']) === 4));

        // RC-02 was left blank in the first cycle; every other level is recorded.
        $missing = $history->flatMap(fn (array $element) => collect($element['levels'])
            ->filter(fn (?int $level): bool => $level === null)
            ->map(fn (?int $level, int $cycle): string => "{$element['code']}@{$cycle}"))->values()->all();
        $this->assertSame(['RC-02@0'], $missing);

        $declined = $history->filter(fn (array $element): bool => ($element['latest_delta'] ?? 0) < 0)->pluck('code')->values()->all();
        $this->assertSame(['PR-02', 'PR-04', 'PR-07', 'PR-10', 'PR-13', 'PR-15', 'PR-18', 'RS-01'], $declined);
        $this->assertSame(8, $response->viewData('decliningElementCount'));

        $response->assertSeeText('Element maturity across cycles')->assertSeeText('Acknowledge all (10)');
    }

    public function test_executive_reviewer_sees_alerts_without_links_to_cycle_results(): void
    {
        $alert = DegradationAlert::factory()->create();

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get('/trends')
            ->assertOk()
            ->assertSeeText($alert->trend->assessment->cycle_label)
            ->assertDontSee(route('assessments.show', $alert->trend->assessment));
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
