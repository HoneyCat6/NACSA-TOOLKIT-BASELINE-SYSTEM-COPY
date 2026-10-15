<?php

namespace Tests\Feature\Reports;

use App\Enums\Role;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\ExecutiveReport;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExecutiveReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ReportService::Disk);
        $this->seed(NcsbElementSeeder::class);
    }

    /**
     * @return array<string, array{Role, bool, bool}>
     */
    public static function matrix(): array
    {
        // role => [generate, view & download]
        return [
            'admin' => [Role::Admin, false, false],
            'compliance officer' => [Role::ComplianceOfficer, true, true],
            'executive reviewer' => [Role::ExecutiveReviewer, false, true],
        ];
    }

    /**
     * PRD §4.2 permission matrix (SEC-03).
     */
    #[DataProvider('matrix')]
    public function test_role_permissions(Role $role, bool $canGenerate, bool $canDownload): void
    {
        $user = User::factory()->create(['role' => $role]);
        $report = $this->generateReport();

        $this->assertSame($canGenerate, $user->can('create', ExecutiveReport::class));
        $this->assertSame($canDownload, $user->can('viewAny', ExecutiveReport::class));
        $this->assertSame($canDownload, $user->can('download', $report));
    }

    /**
     * FR-33, FR-35, SEC-05: the PDF is stored privately, the payload is encrypted and the generation time is audited.
     */
    public function test_officer_generates_a_report(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $assessment = Assessment::factory()->withElements(['GV-01' => 0])->create(['cycle_label' => 'Cycle 2026 H1']);

        $this->signIn($officer)
            ->post(route('reports.generate', $assessment))
            ->assertRedirect(route('reports.index').'#cycle-'.$assessment->id)
            ->assertSessionHas('status', 'Version 1 of the Cycle 2026 H1 executive report is ready to download.');

        $report = ExecutiveReport::query()->sole();
        $this->assertSame($officer->id, $report->generated_by);
        $this->assertStringStartsWith('reports/', $report->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk(ReportService::Disk)->get($report->file_path));
        $this->assertSame('GV-01', $report->payload['actions'][0]['code']);

        $rawPayload = DB::table('executive_reports')->value('payload');
        $this->assertStringNotContainsString('Cybersecurity governance', $rawPayload);
        $this->assertNull(json_decode($rawPayload, true)['actions'] ?? null);

        $audit = AuditLog::query()->where('action', 'report.generate')->sole();
        $this->assertSame($officer->id, $audit->user_id);
        $this->assertSame('compliance', $audit->category->value);
        $this->assertSame(1, $audit->metadata['version']);
        $this->assertLessThan(5000, $audit->metadata['duration_ms'], 'FR-35: generation must finish within 5 s.');
    }

    /**
     * FR-34 / DECISIONS D-56: a new version needs confirmation and never touches the earlier one.
     */
    public function test_a_second_report_for_a_cycle_needs_confirmation_and_keeps_the_first(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $assessment = Assessment::factory()->withElements()->create(['cycle_label' => 'Cycle 2026 H1']);
        $this->signIn($officer)->post(route('reports.generate', $assessment));
        $first = ExecutiveReport::query()->sole();
        $firstPdf = Storage::disk(ReportService::Disk)->get($first->file_path);

        Setting::store(Setting::OrganisationName, 'Renamed Organisation');

        $this->signIn($officer)
            ->post(route('reports.generate', $assessment))
            ->assertSessionHasErrors('confirm_new_version');
        $this->assertSame(1, ExecutiveReport::query()->count());

        $this->signIn($officer)
            ->post(route('reports.generate', $assessment), ['confirm_new_version' => '1'])
            ->assertSessionHas('status', 'Version 2 of the Cycle 2026 H1 executive report is ready to download.');

        $second = ExecutiveReport::query()->latest('id')->first();
        $this->assertNotSame($first->file_path, $second->file_path);
        $this->assertSame(2, $second->payload['version']);
        $this->assertSame('Renamed Organisation', $second->payload['organisation']);
        $this->assertSame($firstPdf, Storage::disk(ReportService::Disk)->get($first->file_path));
        $this->assertNotSame('Renamed Organisation', $first->fresh()->payload['organisation']);
    }

    public function test_executive_reviewer_cannot_generate_a_report(): void
    {
        $this->signIn(User::factory()->executiveReviewer()->create())
            ->post(route('reports.generate', Assessment::factory()->withElements()->create()))
            ->assertForbidden();

        $this->assertSame(0, ExecutiveReport::query()->count());
    }

    /**
     * @return array<string, array{Role}>
     */
    public static function readers(): array
    {
        return [
            'compliance officer' => [Role::ComplianceOfficer],
            'executive reviewer' => [Role::ExecutiveReviewer],
        ];
    }

    #[DataProvider('readers')]
    public function test_officers_and_executives_download_the_stored_pdf(Role $role): void
    {
        $report = $this->generateReport('Cycle 2026 H1');
        $reader = User::factory()->create(['role' => $role]);

        $response = $this->signIn($reader)->get(route('reports.download', $report));

        $response->assertOk()->assertDownload('executive-summary-cycle-2026-h1-v1.pdf');
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame(Storage::disk(ReportService::Disk)->get($report->file_path), $response->streamedContent());
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'report.downloaded',
            'user_id' => $reader->id,
            'auditable_id' => $report->id,
        ]);
    }

    public function test_admin_cannot_view_or_download_reports(): void
    {
        $admin = User::factory()->admin()->create();
        $report = $this->generateReport();

        $this->signIn($admin)->get(route('reports.index'))->assertForbidden();
        $this->signIn($admin)->get(route('reports.download', $report))->assertForbidden();
    }

    public function test_download_of_a_missing_file_is_not_found(): void
    {
        $report = $this->generateReport();
        Storage::disk(ReportService::Disk)->delete($report->file_path);

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get(route('reports.download', $report))
            ->assertNotFound();
    }

    public function test_officer_sees_every_cycle_with_its_versions_newest_first(): void
    {
        $this->generateReport('Cycle 2025 H2', '2025-12-15');
        Assessment::factory()->withElements()->create(['cycle_label' => 'Cycle 2026 H1', 'assessment_date' => '2026-06-15']);

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSeeInOrder(['Cycle 2026 H1', 'Generate report', 'Cycle 2025 H2', 'Generate new version', 'Version 1', 'Download PDF'])
            ->assertSeeText('No report generated for this cycle yet.');
    }

    /**
     * DECISIONS D-37: executives see reports only — no generate button, no cycle without a report, no links to results.
     */
    public function test_executive_sees_only_cycles_that_have_a_report(): void
    {
        $report = $this->generateReport('Cycle 2025 H2', '2025-12-15');
        Assessment::factory()->withElements()->create(['cycle_label' => 'Cycle 2026 H1', 'assessment_date' => '2026-06-15']);

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSeeText('Cycle 2025 H2')
            ->assertDontSeeText('Cycle 2026 H1')
            ->assertDontSeeText('Generate report')
            ->assertDontSeeText('Generate new version')
            ->assertSee(route('reports.download', $report))
            ->assertDontSee(route('assessments.show', $report->assessment));
    }

    public function test_reports_page_has_empty_states_for_each_role(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get(route('reports.index'))
            ->assertSeeText('No assessment cycles yet');

        Assessment::factory()->withElements()->create();

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get(route('reports.index'))
            ->assertSeeText('No executive reports yet');
    }

    public function test_executive_dashboard_links_to_the_latest_report(): void
    {
        $this->generateReport('Cycle 2025 H2', '2025-12-15');
        $latest = $this->generateReport('Cycle 2026 H1', '2026-06-15');

        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Latest executive report')
            ->assertSee(route('reports.download', $latest));
    }

    /**
     * FR-33: cover page, domain risk table, narratives, ranked actions and the Act status with its footnote.
     */
    public function test_pdf_template_renders_every_required_section(): void
    {
        Setting::store(Setting::OrganisationName, 'Malaysia Airports Holdings Berhad');
        $assessment = Assessment::factory()
            ->withElements(['GV-01' => 0, 'PR-01' => 1, 'RC-01' => null], defaultLevel: 3)
            ->create(['cycle_label' => 'Cycle 2026 H1', 'assessment_date' => '2026-06-15']);
        $payload = $this->app->make(ReportService::class)
            ->buildPayload($assessment, User::factory()->create(['name' => 'Aina Officer']), now());

        $this->view('reports.pdf', ['report' => $payload])
            ->assertSeeInOrder([
                'Malaysia Airports Holdings Berhad',
                'Cybersecurity Executive Summary',
                'Cycle 2026 H1',
                '15 June 2026',
                $payload['overall_band'],
                $payload['compliance_status'],
                'Aina Officer',
                'Risk by area',
                'Cybersecurity governance',
                'What this means for the organisation',
                'Priority actions',
                'Cyber Security Policy',
                'Initial (0)',
                'Basic (1)',
                'Not assessed in this cycle',
                'RC-01',
                'It is not a legal determination of compliance.',
            ]);
    }

    /**
     * DECISIONS D-35: undoing an upload also removes its reports and their PDFs.
     */
    public function test_deleting_a_cycle_removes_its_reports_and_files(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $report = $this->generateReport();
        $assessment = $report->assessment;

        $this->signIn($officer)
            ->delete(route('assessments.destroy', $assessment), ['reason' => 'Wrong file uploaded'])
            ->assertRedirect(route('assessments.index'));

        $this->assertModelMissing($report);
        Storage::disk(ReportService::Disk)->assertMissing($report->file_path);
        $this->assertSame(1, AuditLog::query()->where('action', 'assessment.deleted')->sole()->metadata['reports_deleted']);
    }

    private function generateReport(string $cycleLabel = 'Cycle 2026 H1', ?string $assessmentDate = null): ExecutiveReport
    {
        $assessment = Assessment::factory()->withElements()->create(array_filter([
            'cycle_label' => $cycleLabel,
            'assessment_date' => $assessmentDate,
        ]));

        return $this->app->make(ReportService::class)->generate($assessment, User::factory()->complianceOfficer()->create());
    }
}
