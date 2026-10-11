<?php

namespace Tests\Feature\Assessments;

use App\Enums\Band;
use App\Mail\DegradationAlertMail;
use App\Models\Assessment;
use App\Models\User;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ToolkitWorkbook;
use Tests\TestCase;

class UploadAssessmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NcsbElementSeeder::class);
    }

    public function test_officer_upload_scores_and_stores_the_assessment(): void
    {
        Storage::fake('local');
        $officer = User::factory()->complianceOfficer()->create();

        $response = $this->signIn($officer)->post('/assessments', $this->payload(
            ToolkitWorkbook::allYes()->withYesCount('GV-01', 3)->toUploadedFile(),
        ));

        $assessment = Assessment::query()->sole();
        $response->assertRedirect(route('assessments.show', $assessment));

        $this->assertSame('Cycle 2026 H1', $assessment->cycle_label);
        $this->assertSame($officer->id, $assessment->created_by);
        $this->assertSame('1.000', $assessment->overall_score);
        $this->assertSame(Band::Advanced, $assessment->overall_band);
        $this->assertSame('2.86', $assessment->govern_score);
        $this->assertSame('3.00', $assessment->protect_score);
        $this->assertSame(33, $assessment->elements()->count());
        Storage::disk('local')->assertExists($assessment->stored_path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'assessment.upload',
            'user_id' => $officer->id,
            'auditable_id' => $assessment->id,
            'category' => 'compliance',
        ]);
    }

    public function test_element_responses_are_encrypted_at_rest(): void
    {
        Storage::fake('local');

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->post('/assessments', $this->payload(ToolkitWorkbook::allYes()->toUploadedFile()));

        $stored = DB::table('assessment_elements')->where('element_code', 'GV-01')->value('responses');

        $this->assertNull(json_decode($stored, true));
        $this->assertStringNotContainsString('true', $stored);
        $this->assertSame([true, true, true, true, true], Assessment::query()->sole()->elements()->where('element_code', 'GV-01')->sole()->responses);
    }

    public function test_second_cycle_with_a_decline_records_the_trend_and_emails_active_officers(): void
    {
        Storage::fake('local');
        Mail::fake();
        $officer = User::factory()->complianceOfficer()->create();
        $otherOfficer = User::factory()->complianceOfficer()->create();
        $inactiveOfficer = User::factory()->complianceOfficer()->inactive()->create();
        $executive = User::factory()->executiveReviewer()->create();

        $this->signIn($officer)->post('/assessments', $this->payload(ToolkitWorkbook::allYes()->toUploadedFile(), 'Cycle 2025 H2', '2025-12-01'));
        $this->signIn($officer)->post('/assessments', $this->payload(
            ToolkitWorkbook::allYes()->withYesCount('RS-01', 1)->toUploadedFile(),
            'Cycle 2026 H1',
            '2026-06-01',
        ));

        $current = Assessment::query()->where('cycle_label', 'Cycle 2026 H1')->sole();
        $this->assertSame('-2.00', $current->trend->respond_delta);
        $this->assertSame('0.00', $current->trend->protect_delta);
        $this->assertSame(['RESPOND'], $current->trend->alerts->pluck('domain.value')->all());

        Mail::assertSent(DegradationAlertMail::class, 2);
        Mail::assertSent(DegradationAlertMail::class, fn (DegradationAlertMail $mail) => $mail->hasTo($officer->email));
        Mail::assertSent(DegradationAlertMail::class, fn (DegradationAlertMail $mail) => $mail->hasTo($otherOfficer->email));
        Mail::assertNotSent(DegradationAlertMail::class, fn (DegradationAlertMail $mail) => $mail->hasTo($inactiveOfficer->email) || $mail->hasTo($executive->email));
    }

    public function test_second_cycle_without_a_decline_sends_no_alert(): void
    {
        Storage::fake('local');
        Mail::fake();
        $officer = User::factory()->complianceOfficer()->create();

        $this->signIn($officer)->post('/assessments', $this->payload(ToolkitWorkbook::allYes()->withYesCount('RS-01', 1)->toUploadedFile(), 'Cycle 2025 H2', '2025-12-01'));
        $this->signIn($officer)->post('/assessments', $this->payload(ToolkitWorkbook::allYes()->toUploadedFile(), 'Cycle 2026 H1', '2026-06-01'));

        $this->assertDatabaseCount('assessment_trends', 1);
        $this->assertDatabaseCount('degradation_alerts', 0);
        Mail::assertNothingSent();
    }

    public function test_template_failure_is_reported_audited_and_nothing_is_persisted(): void
    {
        Storage::fake('local');
        $officer = User::factory()->complianceOfficer()->create();
        $file = ToolkitWorkbook::allYes()->renameSheet('CS Baseline Questionnaires', 'PROTECT')->toUploadedFile('modified.xlsx');

        $this->signIn($officer)->from('/assessments/upload')->post('/assessments', $this->payload($file))
            ->assertRedirect('/assessments/upload')
            ->assertSessionHasErrors(['file' => "Sheet 'CS Baseline Questionnaires' missing. Upload the unmodified NCSB v1.4 toolkit."])
            ->assertSessionHasInput('cycle_label', 'Cycle 2026 H1');

        $this->assertDatabaseCount('assessments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseHas('audit_logs', ['action' => 'assessment.rejected', 'user_id' => $officer->id, 'category' => 'compliance']);
        $this->assertSame(
            ['filename' => 'modified.xlsx', 'reason' => "Sheet 'CS Baseline Questionnaires' missing. Upload the unmodified NCSB v1.4 toolkit."],
            json_decode(DB::table('audit_logs')->where('action', 'assessment.rejected')->value('metadata'), true),
        );
    }

    public function test_rejects_a_file_that_was_already_uploaded(): void
    {
        Storage::fake('local');
        $officer = User::factory()->complianceOfficer()->create();
        $first = ToolkitWorkbook::allYes()->toUploadedFile();
        $copyPath = tempnam(sys_get_temp_dir(), 'ncsb');
        copy($first->getRealPath(), $copyPath);

        $this->travelTo('2026-02-14 10:00');
        $this->signIn($officer)->post('/assessments', $this->payload($first, 'Cycle 2025 H2', '2025-12-01'));

        $this->signIn($officer)->post('/assessments', $this->payload(
            new UploadedFile($copyPath, 'renamed-copy.xlsx', ToolkitWorkbook::MimeType, null, true),
            'Cycle 2026 H1',
            '2026-02-01',
        ))->assertSessionHasErrors(['file' => 'This exact file was already uploaded as Cycle 2025 H2 on 14 Feb 2026.']);

        $this->assertDatabaseCount('assessments', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assessment.rejected']);
    }

    public function test_rejects_an_assessment_date_not_later_than_the_latest_cycle(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        Assessment::factory()->create(['assessment_date' => '2026-03-01', 'cycle_label' => 'Cycle 2026 Q1']);

        $this->signIn($officer)->post('/assessments', $this->payload(ToolkitWorkbook::allYes()->toUploadedFile(), 'Cycle 2025 H2', '2026-03-01'))
            ->assertSessionHasErrors(['assessment_date' => 'The assessment date must be later than the latest cycle, Cycle 2026 Q1 (1 Mar 2026).']);

        $this->assertDatabaseCount('assessments', 1);
    }

    public function test_rejects_a_future_assessment_date(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->post('/assessments', $this->payload(ToolkitWorkbook::allYes()->toUploadedFile(), date: now()->addDay()->toDateString()))
            ->assertSessionHasErrors(['assessment_date' => 'The assessment date cannot be in the future.']);
    }

    public function test_rejects_a_duplicate_cycle_label(): void
    {
        Assessment::factory()->create(['cycle_label' => 'Cycle 2026 H1', 'assessment_date' => '2025-01-01']);

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->post('/assessments', $this->payload(ToolkitWorkbook::allYes()->toUploadedFile()))
            ->assertSessionHasErrors(['cycle_label' => 'This cycle label is already used by another assessment.']);
    }

    public function test_rejects_a_php_file_renamed_to_xlsx(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        // A real file, not UploadedFile::fake(): fakes report a MIME type from the name instead of sniffing content.
        $path = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($path, '<?php system($_GET["c"]);');
        $file = new UploadedFile($path, 'shell.xlsx', ToolkitWorkbook::MimeType, null, true);

        $this->signIn($officer)->post('/assessments', $this->payload($file))
            ->assertSessionHasErrors(['file' => 'The file is not a valid .xlsx workbook.']);

        $this->assertDatabaseCount('assessments', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'assessment.rejected', 'user_id' => $officer->id]);
    }

    public function test_rejects_a_file_with_the_wrong_extension(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->post('/assessments', $this->payload(UploadedFile::fake()->create('toolkit.csv', 10, 'text/csv')))
            ->assertSessionHasErrors(['file' => 'Only the official NCSB v1.4 toolkit (.xlsx) can be uploaded.']);
    }

    public function test_rejects_a_file_larger_than_10_mb(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->post('/assessments', $this->payload(UploadedFile::fake()->create('toolkit.xlsx', 10241, ToolkitWorkbook::MimeType)))
            ->assertSessionHasErrors(['file' => 'The file must not be larger than 10 MB.']);
    }

    public function test_requires_the_file_cycle_label_and_date(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->post('/assessments', [])
            ->assertSessionHasErrors(['file', 'cycle_label', 'assessment_date']);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_executive_reviewer_cannot_upload(): void
    {
        $this->signIn(User::factory()->executiveReviewer()->create())
            ->post('/assessments', $this->payload(UploadedFile::fake()->create('toolkit.xlsx', 1)))
            ->assertForbidden();
    }

    public function test_results_screen_lists_unanswered_elements_and_defaulted_cells(): void
    {
        Storage::fake('local');
        $officer = User::factory()->complianceOfficer()->create();

        $this->signIn($officer)->post('/assessments', $this->payload(ToolkitWorkbook::allYes()
            ->answerElement('RS-01', [null, null, null])
            ->answerElement('GV-02', ['Yes', null, 'No'])
            ->toUploadedFile()));

        $this->signIn($officer)->get(route('assessments.show', Assessment::query()->sole()))
            ->assertOk()
            ->assertSeeText('1 unanswered element')
            ->assertSeeText('RS-01 Incident Procedure and Handling')
            ->assertSeeText('Defaulted cells')
            ->assertSeeInOrder(['Defaulted cells', '1']);
    }

    /**
     * @return array{file: UploadedFile, cycle_label: string, assessment_date: string}
     */
    private function payload(UploadedFile $file, string $cycleLabel = 'Cycle 2026 H1', string $date = '2026-06-01'): array
    {
        return ['file' => $file, 'cycle_label' => $cycleLabel, 'assessment_date' => $date];
    }
}
