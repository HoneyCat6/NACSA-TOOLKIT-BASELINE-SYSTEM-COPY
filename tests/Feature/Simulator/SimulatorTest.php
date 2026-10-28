<?php

namespace Tests\Feature\Simulator;

use App\Enums\Band;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\SimulationSession;
use App\Models\User;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Scenario Simulator endpoints (FR-60 – FR-65, TECH_SPEC §11.1, DECISIONS D-25, D-26).
 */
class SimulatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{Role, bool}>
     */
    public static function matrix(): array
    {
        return [
            'admin' => [Role::Admin, false],
            'compliance officer' => [Role::ComplianceOfficer, true],
            'executive reviewer' => [Role::ExecutiveReviewer, true],
        ];
    }

    #[DataProvider('matrix')]
    public function test_role_permissions(Role $role, bool $canSimulate): void
    {
        $user = User::factory()->create(['role' => $role]);
        $own = SimulationSession::factory()->create(['created_by' => $user->id]);
        $someoneElses = SimulationSession::factory()->create();

        $this->assertSame($canSimulate, $user->can('viewAny', SimulationSession::class));
        $this->assertSame($canSimulate, $user->can('create', SimulationSession::class));
        $this->assertSame($canSimulate, $user->can('view', $own));
        $this->assertSame($canSimulate, $user->can('delete', $own));
        $this->assertFalse($user->can('view', $someoneElses));
        $this->assertFalse($user->can('delete', $someoneElses));
    }

    public function test_admin_cannot_open_the_simulator(): void
    {
        $this->signIn(User::factory()->admin()->create())
            ->get('/simulator')
            ->assertForbidden();
    }

    public function test_empty_state_when_no_cycles_exist(): void
    {
        $this->signIn(User::factory()->executiveReviewer()->create())
            ->get('/simulator')
            ->assertSeeText('No assessments yet');
    }

    public function test_opens_on_the_latest_cycle_with_sliders_at_baseline_levels(): void
    {
        $this->seed(NcsbElementSeeder::class);
        Assessment::factory()->withElements()->create(['assessment_date' => '2025-06-01']);
        $latest = Assessment::factory()->withElements(['GV-01' => 3, 'RS-01' => null])->create(['assessment_date' => '2026-01-01']);

        $response = $this->signIn(User::factory()->complianceOfficer()->create())->get('/simulator');

        $this->assertTrue($latest->is($response->viewData('baseline')));
        $levels = collect($response->viewData('state')['elements'])->pluck('level', 'code');
        $this->assertCount(33, $levels);
        $this->assertSame(3, $levels['GV-01']);
        $this->assertNull($levels['RS-01']);
        $this->assertSame(2, $levels['PR-09']);
        $response->assertSee('id="slider-GV-01"', false);
    }

    public function test_any_past_cycle_can_be_selected_as_baseline(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $earlier = Assessment::factory()->withElements(defaultLevel: 1)->create(['assessment_date' => '2025-06-01']);
        Assessment::factory()->withElements()->create(['assessment_date' => '2026-01-01']);

        $response = $this->signIn(User::factory()->complianceOfficer()->create())->get('/simulator?baseline='.$earlier->id);

        $this->assertTrue($earlier->is($response->viewData('baseline')));
        $this->assertSame(1, $response->viewData('state')['elements'][0]['level']);
    }

    public function test_unknown_baseline_is_not_found(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())
            ->get('/simulator?baseline=999')
            ->assertNotFound();
    }

    public function test_project_returns_the_projection_contract(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $baseline = Assessment::factory()->withElements(['RS-01' => 0])->create();

        $response = $this->signIn(User::factory()->executiveReviewer()->create())
            ->postJson('/simulator/project', ['baseline_assessment_id' => $baseline->id, 'overrides' => ['RS-01' => 3]]);

        $response->assertOk()->assertExactJson([
            'projected' => [
                'domain_scores' => ['GOVERN' => 2.0, 'IDENTIFY' => 2.0, 'PROTECT' => 2.0, 'DETECT' => 2.0, 'RESPOND' => 3.0, 'RECOVER' => 2.0],
                'domain_deltas' => ['GOVERN' => 0.0, 'IDENTIFY' => 0.0, 'PROTECT' => 0.0, 'DETECT' => 0.0, 'RESPOND' => 3.0, 'RECOVER' => 0.0],
                'overall_score' => 1.0,
                'overall_band' => 'Advanced',
                'overall_delta' => 0.03,
                'elements_at_target' => 1,
            ],
            'baseline' => ['overall_score' => 0.97, 'overall_band' => 'Advanced'],
        ]);
    }

    public function test_project_rejects_unknown_element_codes(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $baseline = Assessment::factory()->withElements()->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->postJson('/simulator/project', ['baseline_assessment_id' => $baseline->id, 'overrides' => ['XX-99' => 3]])
            ->assertJsonValidationErrors(['overrides' => 'Hypothetical levels must be keyed by NCSB element code.']);
    }

    public function test_project_rejects_levels_outside_0_to_3(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $baseline = Assessment::factory()->withElements()->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->postJson('/simulator/project', ['baseline_assessment_id' => $baseline->id, 'overrides' => ['PR-09' => 4]])
            ->assertJsonValidationErrors(['overrides.PR-09' => 'Each hypothetical level must be between 0 and 3.']);
    }

    /**
     * FR-64, FR-06, SEC-05: saving recomputes on the server, encrypts the levels and audits the save.
     */
    public function test_saving_a_scenario_persists_it_and_audits_the_save(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $baseline = Assessment::factory()->withElements(['RS-01' => 0])->create(['cycle_label' => 'Cycle 2026 H1']);
        $executive = User::factory()->executiveReviewer()->create();

        $response = $this->signIn($executive)->post('/simulator/sessions', [
            'baseline_assessment_id' => $baseline->id,
            'scenario_name' => 'Fund incident response',
            'overrides' => ['RS-01' => '3', 'GV-01' => '2'],
        ]);

        $session = SimulationSession::query()->sole();
        $response->assertRedirect(route('simulator.sessions.show', $session))
            ->assertSessionHas('status', 'Scenario "Fund incident response" was saved.');

        $this->assertTrue($session->creator->is($executive));
        $this->assertTrue($session->baseline->is($baseline));
        $this->assertSame('Fund incident response', $session->scenario_name);
        $this->assertSame(['RS-01' => 3], $session->modified_responses);
        $this->assertSame('1.000', $session->simulated_score);
        $this->assertSame(Band::Advanced, $session->simulated_band);

        $stored = DB::table('simulation_sessions')->value('modified_responses');
        $this->assertNull(json_decode($stored, true));
        $this->assertStringNotContainsString('RS-01', $stored);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'simulation.save',
            'user_id' => $executive->id,
            'auditable_id' => $session->id,
            'category' => 'compliance',
        ]);
        $this->assertSame([
            'scenario_name' => 'Fund incident response',
            'baseline_cycle' => 'Cycle 2026 H1',
            'elements_changed' => 1,
            'simulated_score' => 1,
            'simulated_band' => 'Advanced',
        ], json_decode(DB::table('audit_logs')->where('action', 'simulation.save')->value('metadata'), true));
    }

    public function test_saving_needs_a_name_and_at_least_one_change(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $baseline = Assessment::factory()->withElements()->create();

        $this->signIn(User::factory()->complianceOfficer()->create())
            ->post('/simulator/sessions', ['baseline_assessment_id' => $baseline->id])
            ->assertSessionHasErrors([
                'scenario_name' => 'Give the scenario a name.',
                'overrides' => 'Move at least one slider away from the baseline before saving a scenario.',
            ]);

        $this->assertDatabaseCount('simulation_sessions', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'simulation.save']);
    }

    /**
     * FR-63 regression: projecting, saving, reloading and deleting never touch assessment data.
     */
    public function test_simulation_never_mutates_assessment_data(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $baseline = Assessment::factory()->withElements(['RS-01' => 0, 'GV-01' => null])->create();
        $snapshot = fn (): array => [
            DB::table('assessments')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
            DB::table('assessment_elements')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all(),
        ];
        $before = $snapshot();
        $overrides = ['RS-01' => 3, 'GV-01' => 1, 'PR-09' => 0];
        $this->signIn(User::factory()->complianceOfficer()->create());

        $this->postJson('/simulator/project', ['baseline_assessment_id' => $baseline->id, 'overrides' => $overrides])->assertOk();
        $this->post('/simulator/sessions', ['baseline_assessment_id' => $baseline->id, 'scenario_name' => 'What if', 'overrides' => $overrides]);
        $session = SimulationSession::query()->sole();
        $this->get(route('simulator.sessions.show', $session))->assertOk();
        $this->delete(route('simulator.sessions.destroy', $session));

        $this->assertSame($before, $snapshot());
    }

    public function test_saved_scenario_reloads_with_its_levels_on_its_baseline(): void
    {
        $this->seed(NcsbElementSeeder::class);
        $officer = User::factory()->complianceOfficer()->create();
        $baseline = Assessment::factory()->withElements()->create(['assessment_date' => '2025-06-01']);
        Assessment::factory()->withElements()->create(['assessment_date' => '2026-01-01']);
        $session = SimulationSession::factory()->create([
            'created_by' => $officer->id,
            'baseline_assessment_id' => $baseline->id,
            'scenario_name' => 'Close PROTECT gaps',
            'modified_responses' => ['PR-09' => 3, 'PR-10' => 3],
        ]);

        $response = $this->signIn($officer)->get(route('simulator.sessions.show', $session));

        $response->assertSeeText('Close PROTECT gaps');
        $this->assertTrue($baseline->is($response->viewData('baseline')));
        $this->assertSame(['PR-09' => 3, 'PR-10' => 3], (array) $response->viewData('initialOverrides'));
    }

    public function test_another_users_scenario_cannot_be_opened_or_deleted(): void
    {
        $session = SimulationSession::factory()->create();
        $this->signIn(User::factory()->complianceOfficer()->create());

        $this->get(route('simulator.sessions.show', $session))->assertForbidden();
        $this->delete(route('simulator.sessions.destroy', $session))->assertForbidden();

        $this->assertModelExists($session);
    }

    public function test_saved_scenarios_list_shows_only_the_users_own(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        SimulationSession::factory()->create(['created_by' => $officer->id, 'scenario_name' => 'Mine']);
        SimulationSession::factory()->create(['scenario_name' => 'Colleague']);

        $response = $this->signIn($officer)->get('/simulator');

        $response->assertSeeText('Mine')->assertDontSeeText('Colleague');
    }

    public function test_author_deletes_a_scenario_and_the_deletion_is_audited(): void
    {
        $officer = User::factory()->complianceOfficer()->create();
        $session = SimulationSession::factory()->create(['created_by' => $officer->id, 'scenario_name' => 'Old idea']);

        $this->signIn($officer)
            ->delete(route('simulator.sessions.destroy', $session))
            ->assertRedirect(route('simulator.index'))
            ->assertSessionHas('status', 'Scenario "Old idea" was deleted.');

        $this->assertModelMissing($session);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'simulation.deleted',
            'user_id' => $officer->id,
            'auditable_id' => $session->id,
        ]);
    }

    public function test_deleting_the_baseline_cycle_removes_its_scenarios(): void
    {
        Storage::fake('local');
        $officer = User::factory()->complianceOfficer()->create();
        $baseline = Assessment::factory()->create();
        $session = SimulationSession::factory()->create(['created_by' => $officer->id, 'baseline_assessment_id' => $baseline->id]);

        $this->signIn($officer)->delete(route('assessments.destroy', $baseline), ['reason' => 'Wrong file uploaded']);

        $this->assertModelMissing($session);
    }
}
