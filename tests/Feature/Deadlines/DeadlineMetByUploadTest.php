<?php

namespace Tests\Feature\Deadlines;

use App\Enums\DeadlineStatus;
use App\Models\Assessment;
use App\Models\ComplianceDeadline;
use App\Models\User;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ToolkitWorkbook;
use Tests\TestCase;

/**
 * DECISIONS D-40: a validated upload meets open deadlines; D-46: deleting that cycle reopens them.
 */
class DeadlineMetByUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-01 10:00:00');
        $this->seed(NcsbElementSeeder::class);
    }

    public function test_upload_meets_open_deadlines_that_are_not_overdue(): void
    {
        Storage::fake('local');
        $officer = User::factory()->complianceOfficer()->create();
        $dueToday = ComplianceDeadline::factory()->dueIn(0)->create();
        $dueLater = ComplianceDeadline::factory()->dueIn(20)->create();
        $overdue = ComplianceDeadline::factory()->dueIn(-1)->create();

        $this->upload($officer);

        $assessment = Assessment::query()->sole();
        foreach ([$dueToday, $dueLater] as $deadline) {
            $deadline->refresh();
            $this->assertSame(DeadlineStatus::Met, $deadline->status);
            $this->assertSame($assessment->id, $deadline->met_by_assessment_id);
        }
        $this->assertSame(DeadlineStatus::Overdue, $overdue->fresh()->status);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'deadline.met')->where('user_id', $officer->id)->count());
    }

    public function test_a_rejected_upload_meets_nothing(): void
    {
        Storage::fake('local');
        $officer = User::factory()->complianceOfficer()->create();
        $deadline = ComplianceDeadline::factory()->dueIn(20)->create();

        $this->signIn($officer)->post(route('assessments.store'), [
            'file' => ToolkitWorkbook::allYes()->renameSheet('CS Baseline Questionnaires', 'Renamed')->toUploadedFile(),
            'cycle_label' => 'Cycle 2026 H2',
            'assessment_date' => '2026-10-01',
        ])->assertSessionHasErrors('file');

        $this->assertNull($deadline->fresh()->met_at);
    }

    /**
     * D-46: undoing the upload reopens its deadlines; ones met by hand stay met.
     */
    public function test_deleting_the_cycle_reopens_the_deadlines_it_met(): void
    {
        Storage::fake('local');
        $officer = User::factory()->complianceOfficer()->create();
        $metByUpload = ComplianceDeadline::factory()->dueIn(20)->create();
        $this->upload($officer);
        $metByHand = ComplianceDeadline::factory()->dueIn(20)->met()->create();
        $assessment = Assessment::query()->sole();

        $this->signIn($officer)->delete(route('assessments.destroy', $assessment), ['reason' => 'Wrong file uploaded'])
            ->assertRedirect(route('assessments.index'));

        $metByUpload->refresh();
        $this->assertNull($metByUpload->met_at);
        $this->assertNull($metByUpload->met_by_assessment_id);
        $this->assertSame(DeadlineStatus::Upcoming, $metByUpload->status);
        $this->assertSame(DeadlineStatus::Met, $metByHand->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deadline.reopened', 'auditable_id' => $metByUpload->id]);
        $this->assertSame(1, json_decode(DB::table('audit_logs')->where('action', 'assessment.deleted')->value('metadata'), true)['deadlines_reopened']);
    }

    private function upload(User $officer): void
    {
        $this->signIn($officer)->post(route('assessments.store'), [
            'file' => ToolkitWorkbook::allYes()->toUploadedFile(),
            'cycle_label' => 'Cycle 2026 H2',
            'assessment_date' => '2026-10-01',
        ])->assertSessionHasNoErrors();
    }
}
