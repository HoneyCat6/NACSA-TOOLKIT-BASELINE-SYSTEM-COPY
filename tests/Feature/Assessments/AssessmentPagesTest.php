<?php

namespace Tests\Feature\Assessments;

use App\Enums\AuditCategory;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_lists_every_cycle_newest_first(): void
    {
        $uploader = User::factory()->complianceOfficer()->create(['name' => 'Aina Officer']);
        Assessment::factory()->for($uploader, 'creator')->create(['cycle_label' => 'Cycle 2025 H1', 'assessment_date' => '2025-03-01']);
        Assessment::factory()->for($uploader, 'creator')->create(['cycle_label' => 'Cycle 2025 H2', 'assessment_date' => '2025-09-01']);

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get('/assessments')
            ->assertOk()
            ->assertSeeInOrder(['Cycle 2025 H2', 'Cycle 2025 H1'])
            ->assertSeeText('Aina Officer')
            ->assertSeeText('Validated')
            ->assertDontSeeText('Upload assessment');
    }

    public function test_history_shows_an_empty_state_with_no_cycles(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/assessments')
            ->assertOk()
            ->assertSeeText('No assessment cycles yet');
    }

    public function test_admin_cannot_view_assessments(): void
    {
        $this->signIn(User::factory()->admin()->create())->get('/assessments')->assertForbidden();
    }

    public function test_upload_screen_lists_recent_rejections(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        AuditLog::query()->create([
            'user_id' => $officer->id,
            'action' => 'assessment.rejected',
            'category' => AuditCategory::Compliance,
            'metadata' => ['filename' => 'old-toolkit-v1.1.xlsx', 'reason' => "Sheet 'CS Baseline Model' missing."],
            'created_at' => now(),
        ]);

        $this->signIn($officer)->get('/assessments/upload')
            ->assertOk()
            ->assertSeeText('Template: NCSB v1.4')
            ->assertSeeText('old-toolkit-v1.1.xlsx')
            ->assertSeeText("Sheet 'CS Baseline Model' missing.");
    }

    public function test_executive_reviewer_cannot_open_the_upload_screen(): void
    {
        $this->signIn(User::factory()->executiveReviewer()->create())->get('/assessments/upload')->assertForbidden();
    }

    public function test_executive_reviewer_can_open_a_result(): void
    {
        $assessment = Assessment::factory()->create(['cycle_label' => 'Cycle 2026 H1']);

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get(route('assessments.show', $assessment))
            ->assertOk()
            ->assertSeeText('Cycle 2026 H1')
            ->assertSeeText('Share of the 33 elements with at least one control implemented')
            ->assertSeeText('Average element maturity within each domain (0–3)');
    }
}
