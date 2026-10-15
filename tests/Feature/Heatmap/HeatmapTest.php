<?php

namespace Tests\Feature\Heatmap;

use App\Enums\Domain;
use App\Enums\Severity;
use App\Models\Assessment;
use App\Models\User;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_view_the_heatmap(): void
    {
        $this->signIn(User::factory()->admin()->create())
            ->get('/heatmap')
            ->assertForbidden();
    }

    public function test_officer_and_executive_can_view_the_heatmap(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())->get('/heatmap')->assertOk();
        $this->signIn(User::factory()->executiveReviewer()->create())->get('/heatmap')->assertOk();
    }

    public function test_empty_state_when_no_cycles_exist(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/heatmap')
            ->assertOk()
            ->assertSeeText('No assessments yet');
    }

    public function test_grid_renders_all_33_elements_grouped_by_the_six_domains(): void
    {
        $this->seed(NcsbElementSeeder::class);
        Assessment::factory()->withElements()->create();

        $grid = $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/heatmap')
            ->assertOk()
            ->viewData('grid');

        $this->assertCount(6, $grid);
        $this->assertSame(33, collect($grid)->sum(fn (array $group): int => count($group['cells'])));
    }

    public function test_legend_lists_every_severity(): void
    {
        $this->seed(NcsbElementSeeder::class);
        Assessment::factory()->withElements()->create();

        $response = $this->signIn(User::factory()->complianceOfficer()->create())->get('/heatmap')->assertOk();

        foreach (Severity::cases() as $severity) {
            $response->assertSeeText($severity->label());
        }
    }

    public function test_unanswered_element_is_grey_and_labelled_not_critical(): void
    {
        $this->seed(NcsbElementSeeder::class);
        Assessment::factory()->withElements(['GV-01' => null])->create();

        $grid = $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/heatmap')
            ->assertOk()
            ->viewData('grid');

        $cell = collect($grid[Domain::Govern->value]['cells'])->firstWhere('code', 'GV-01');

        $this->assertNull($cell['level']);
        $this->assertSame(Severity::Unanswered, $cell['severity']);
        $this->assertNotSame(Severity::Critical, $cell['severity']);
    }

    public function test_cycle_selector_replays_a_past_cycle(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $older = Assessment::factory()->withElements([], 1)->create(['assessment_date' => '2025-03-01']);
        Assessment::factory()->withElements([], 3)->create(['assessment_date' => '2026-03-01']);

        $officer = User::factory()->complianceOfficer()->create();

        // Default is the latest cycle.
        $latestView = $this->signIn($officer)->get('/heatmap')->assertOk();
        $this->assertTrue($latestView->viewData('cycle')->assessment_date->isSameDay('2026-03-01'));

        // ?cycle= replays the older one (FR-53).
        $olderView = $this->signIn($officer)->get("/heatmap?cycle={$older->id}")->assertOk();
        $this->assertSame($older->id, $olderView->viewData('cycle')->id);
    }

    public function test_unknown_cycle_is_404(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/heatmap?cycle=999999')
            ->assertNotFound();
    }

    public function test_detail_returns_the_drill_down_contract(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $assessment = Assessment::factory()->withElements(['GV-01' => 1])->create();

        $response = $this->signIn(User::factory()->executiveReviewer()->create())
            ->getJson("/heatmap/element/GV-01?cycle={$assessment->id}")
            ->assertOk()
            ->assertJsonStructure([
                'code', 'name', 'domain', 'category', 'maturity_level', 'level_label',
                'gap_from_target', 'severity', 'severity_label', 'yes_count', 'question_count', 'remediation_note',
            ])
            ->assertJson([
                'code' => 'GV-01',
                'maturity_level' => 1,
                'level_label' => 'Basic',
                'gap_from_target' => 2,
                'severity' => 'high',
                'severity_label' => 'High Risk',
            ]);

        $this->assertStringContainsString('To reach Level 2 (Intermediate):', $response->json('remediation_note'));
    }

    public function test_detail_for_an_unanswered_element_says_to_assess_it(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $assessment = Assessment::factory()->withElements(['GV-01' => null])->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->getJson("/heatmap/element/GV-01?cycle={$assessment->id}")
            ->assertOk()
            ->assertJson([
                'maturity_level' => null,
                'gap_from_target' => null,
                'severity' => 'unanswered',
                'remediation_note' => 'Assess this element to determine its maturity.',
            ]);
    }

    public function test_detail_for_an_unknown_code_is_404(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $assessment = Assessment::factory()->withElements()->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->getJson("/heatmap/element/ZZ-99?cycle={$assessment->id}")
            ->assertNotFound();
    }
}
