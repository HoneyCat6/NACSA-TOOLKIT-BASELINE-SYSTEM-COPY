<?php

namespace Tests\Feature;

use App\DataObjects\ParsedAssessment;
use App\Enums\Band;
use App\Enums\Domain;
use App\Enums\ElementStatus;
use App\Enums\MaturityLevel;
use App\Models\Assessment;
use App\Models\AuditLog;
use App\Models\ExecutiveReport;
use App\Models\NcsbElement;
use App\Models\Sector;
use App\Models\SectorConstruct;
use App\Models\SectorCoreConstruct;
use App\Models\User;
use App\Services\ScoringService;
use App\Services\SimulationService;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ToolkitWorkbook;
use Tests\TestCase;

class SectorConstructTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NcsbElementSeeder::class);
    }

    public function test_admin_manages_constructs_per_sector_and_state_changes_are_audited(): void
    {
        $this->signIn($admin = User::factory()->admin()->create())
            ->post(route('admin.sectors.store'), ['name' => 'Energy', 'code_of_practice' => 'Energy CoP v1'])
            ->assertSessionHasNoErrors();

        $sector = Sector::query()->sole();
        $otherSector = Sector::factory()->create();
        $core = NcsbElement::query()->where('code', 'GV-01')->sole();
        $coreRoute = route('admin.constructs.core.update', [$sector, $core]);
        $index = route('admin.constructs.index', $sector);

        $this->patch($coreRoute, ['status' => 'disabled', 'reason' => str_repeat('x', 501)])->assertSessionHasErrors('reason');

        $drop = ['status' => 'disabled', 'reason' => 'Outside the sector CoP scope'];
        $this->patch($coreRoute, $drop)->assertRedirect($index);
        $this->patch($coreRoute, $drop)->assertRedirect($index);

        $this->assertSame(['GV-01'], $sector->disabledCoreCodes());
        $this->assertSame([], $otherSector->disabledCoreCodes());
        $this->assertDatabaseCount('ncsb_elements', 33);
        $this->get($index)->assertOk()->assertSee('Disabled (N/A)');

        $this->patch($coreRoute, ['status' => 'active', 'reason' => 'Scope updated'])->assertRedirect($index);
        $this->assertDatabaseHas('sector_core_constructs', [
            'sector_id' => $sector->id, 'ncsb_element_id' => $core->id, 'status' => 'active',
        ]);

        $extension = ['code' => 'CoP.01', 'name' => 'Substation isolation', 'domain' => Domain::cases()[0]->value, 'requirement' => 'Verify the sector isolation procedure.'];
        $this->post(route('admin.constructs.store', $sector), [...$extension, 'code' => 'GV-01'])->assertSessionHasErrors('code');
        $this->post(route('admin.constructs.store', $sector), $extension)->assertRedirect($index);
        $this->post(route('admin.constructs.store', $sector), $extension)->assertSessionHasErrors('code');
        $this->post(route('admin.constructs.store', $otherSector), $extension)->assertSessionHasNoErrors();

        $audit = AuditLog::query()->where('metadata->sector_id', $sector->id)->where('action', 'like', 'construct.%')->orderBy('id')->get();
        $this->assertSame(['construct.dropped', 'construct.restored', 'construct.added'], $audit->pluck('action')->all());
        $this->assertSame([$admin->id, $admin->id, $admin->id], $audit->pluck('user_id')->all());
        $this->assertSame('Outside the sector CoP scope', $audit[0]->metadata['reason']);
        $this->assertSame('active', $audit[1]->metadata['to']);
        $this->assertSame('CoP.01', $audit[2]->metadata['code']);
        $this->get(route('admin.audit-logs.index'))->assertOk()->assertSee('construct.dropped')->assertSee('construct.restored')->assertSee('construct.added');
    }

    public function test_officers_and_executives_cannot_change_sector_configuration(): void
    {
        $sector = Sector::factory()->create();
        $core = NcsbElement::query()->firstOrFail();

        foreach ([User::factory()->complianceOfficer()->create(), User::factory()->executiveReviewer()->create()] as $user) {
            $this->signIn($user)->get(route('admin.constructs.index', $sector))->assertForbidden();
            $this->post(route('admin.sectors.store'), ['name' => 'Energy'])->assertForbidden();
            $this->patch(route('admin.constructs.core.update', [$sector, $core]), ['status' => 'disabled', 'reason' => 'Scope'])->assertForbidden();
            $this->post(route('admin.constructs.store', $sector))->assertForbidden();
        }

        $this->assertDatabaseEmpty('sector_core_constructs');
        $this->assertDatabaseEmpty('sector_constructs');
    }

    public function test_upload_scores_extensions_separately_and_preserves_cycle_applicability_in_heatmap_and_report(): void
    {
        Storage::fake('local');
        $sector = Sector::factory()->create(['name' => 'Energy']);
        $disabled = SectorCoreConstruct::factory()->for($sector)->for(
            NcsbElement::query()->where('code', 'GV-01')->sole(),
        )->create();
        [$initial, $intermediate, $unassessed] = SectorConstruct::factory()->for($sector)->count(3)->create()->all();
        $intermediate->update(['code' => 'CoP.02']);

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get(route('assessments.upload'))
            ->assertOk()->assertSee('Energy')->assertSee($initial->name);

        $this->post(route('assessments.store'), [
            'file' => ToolkitWorkbook::allYes()->answerElement('GV-01', ['Invalid answer'])->withYesCount('GV-02', 0)->toUploadedFile(),
            'cycle_label' => 'Energy 2026 H1',
            'assessment_date' => '2026-06-01',
            'sector_id' => $sector->id,
            'sector_levels' => [$initial->id => '0', $intermediate->id => '2'],
        ])->assertSessionHasNoErrors();

        $cycle = Assessment::query()->sole();
        $this->assertSame('0.969', $cycle->overall_score);
        $this->assertSame('2.50', $cycle->govern_score);
        $this->assertSame(1.0, $cycle->sectorMaturityAverage());
        $this->assertSame(0, $cycle->unanswered_count);
        $this->assertNull($cycle->parse_warnings);
        $this->assertSame(33, $cycle->elements()->count());
        $this->assertSame(ElementStatus::Disabled, $cycle->elements()->where('element_code', 'GV-01')->sole()->status);
        $this->assertSame(MaturityLevel::Initial, $cycle->sectorConstructScores()->whereBelongsTo($initial)->sole()->maturity_level);
        $this->assertNull($cycle->sectorConstructScores()->whereBelongsTo($unassessed)->sole()->maturity_level);
        $this->assertSame(0.969, app(SimulationService::class)->project($cycle, ['GV-01' => 3])->projectedOverallScore);

        $disabled->update(['status' => SectorCoreConstruct::Active]);
        $laterExtension = SectorConstruct::factory()->for($sector)->create();

        $this->get(route('assessments.show', $cycle))->assertOk()->assertSee('Disabled (N/A)')->assertSee($initial->name);
        $this->get(route('heatmap.index', ['cycle' => $cycle->id]))
            ->assertOk()->assertSee('heatmap-cell--core')->assertSee('heatmap-cell--disabled')->assertSee('heatmap-cell--sector')
            ->assertSee('Sector-specific')->assertDontSee($laterExtension->code);

        $this->getJson(route('heatmap.detail', ['code' => 'GV-01', 'cycle' => $cycle->id]))
            ->assertOk()->assertJsonPath('level_label', 'N/A')->assertJsonPath('maturity_level', null);
        $this->getJson(route('heatmap.detail', ['code' => $intermediate->code, 'cycle' => $cycle->id]))
            ->assertOk()->assertJsonPath('category', 'Sector-specific')->assertJsonPath('maturity_level', 2);

        $this->post(route('reports.generate', $cycle))->assertRedirect();
        $report = ExecutiveReport::query()->sole();
        $this->assertSame(['GV-01'], $report->payload['disabled_core_codes']);
        $this->assertEqualsCanonicalizing(
            [$initial->code, $intermediate->code, $unassessed->code],
            array_column($report->payload['sector_constructs'], 'code'),
        );
        $this->assertStringContainsString('Sector-specific extensions', view('reports.pdf', ['report' => $report->payload])->render());
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($report->file_path));

        $this->post(route('assessments.store'), [
            'file' => ToolkitWorkbook::allYes()->toUploadedFile(),
            'cycle_label' => 'Energy 2026 H2',
            'assessment_date' => '2026-09-01',
            'sector_id' => $sector->id,
        ])->assertSessionHasNoErrors();

        $nextCycle = $cycle->sector->assessments()->newestFirst()->firstOrFail();
        $this->assertSame('1.000', $nextCycle->overall_score);
        $this->assertSame([], $nextCycle->disabledCoreCodes());
        $this->assertNull($nextCycle->trend);
        $this->assertNull($nextCycle->sectorMaturityAverage());
        $this->assertDatabaseHas('sector_construct_scores', [
            'assessment_id' => $nextCycle->id, 'sector_construct_id' => $laterExtension->id, 'maturity_level' => null,
        ]);
    }

    public function test_upload_rejects_extension_ratings_from_another_sector(): void
    {
        Storage::fake('local');
        $sector = Sector::factory()->create();
        $foreignConstruct = SectorConstruct::factory()->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->post(route('assessments.store'), [
                'file' => ToolkitWorkbook::allYes()->toUploadedFile(),
                'cycle_label' => 'Sector 2026 H1',
                'assessment_date' => '2026-06-01',
                'sector_id' => $sector->id,
                'sector_levels' => [$foreignConstruct->id => 3],
            ])->assertSessionHasErrors('sector_levels');

        $this->assertDatabaseEmpty('assessments');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_sector_with_no_applicable_core_constructs_has_no_core_band(): void
    {
        $sector = Sector::factory()->create();
        foreach (NcsbElement::query()->get() as $element) {
            SectorCoreConstruct::factory()->for($sector)->for($element)->create();
        }

        $score = app(ScoringService::class)->score(new ParsedAssessment([], [], 0, []), $sector);

        $this->assertSame(Band::NotApplicable, $score->overallBand);
        $this->assertNull($score->overallScore);
        $this->assertSame([], array_filter($score->domainScores, fn (?float $value): bool => $value !== null));
    }

    public function test_database_rejects_deleting_a_core_element_even_without_assessments(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('NCSB core elements cannot be deleted');

        DB::table('ncsb_elements')->where('code', 'GV-01')->delete();
    }
}
