<?php

namespace Tests\Feature\Reports;

use App\Models\Assessment;
use App\Models\NcsbElement;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReportService;
use App\Services\TrendService;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NcsbElementSeeder::class);
    }

    /**
     * PRD §7.5 / TECH_SPEC §15.2 `ranking_respects_govern_tiebreak`.
     */
    public function test_ranks_by_gap_then_govern_and_respond_then_element_code(): void
    {
        $assessment = Assessment::factory()->withElements([
            'PR-01' => 0,
            'DE-01' => 0,
            'GV-02' => 0,
            'PR-02' => 1,
            'RS-01' => 1,
            'ID-01' => 2,
            'GV-01' => 2,
        ], defaultLevel: 3)->create();

        $actions = $this->app->make(ReportService::class)->prioritise($assessment);

        $this->assertSame(
            ['GV-02', 'DE-01', 'PR-01', 'RS-01', 'PR-02', 'GV-01', 'ID-01'],
            array_column($actions, 'code'),
        );
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], array_column($actions, 'rank'));
        $this->assertSame([3, 3, 3, 2, 2, 1, 1], array_column($actions, 'gap'));
    }

    public function test_takes_the_top_ten_and_leaves_out_advanced_and_unanswered_elements(): void
    {
        $assessment = Assessment::factory()->withElements([
            'GV-01' => 3,
            'GV-02' => null,
            'RS-01' => 3,
        ], defaultLevel: 1)->create();

        $actions = $this->app->make(ReportService::class)->prioritise($assessment);

        $this->assertCount(10, $actions);
        $this->assertSame(['GV-03', 'GV-04', 'GV-05', 'GV-06', 'GV-07'], array_slice(array_column($actions, 'code'), 0, 5));
        $this->assertEmpty(array_intersect(['GV-01', 'GV-02', 'RS-01'], array_column($actions, 'code')));
    }

    public function test_each_action_states_the_element_its_level_and_the_next_level_requirement_verbatim(): void
    {
        $assessment = Assessment::factory()->withElements(['GV-01' => 1, 'ID-01' => 0], defaultLevel: 3)->create();

        $actions = collect($this->app->make(ReportService::class)->prioritise($assessment))->keyBy('code');
        $policy = NcsbElement::query()->where('code', 'GV-01')->sole();
        $assets = NcsbElement::query()->where('code', 'ID-01')->sole();

        $this->assertSame('Cyber Security Policy', $actions['GV-01']['name']);
        $this->assertSame([1, 'Basic', 'Intermediate'], [$actions['GV-01']['level'], $actions['GV-01']['level_label'], $actions['GV-01']['target_label']]);
        $this->assertSame($policy->requirement_l2, $actions['GV-01']['action']);

        $this->assertSame([0, 'Initial', 'Basic'], [$actions['ID-01']['level'], $actions['ID-01']['level_label'], $actions['ID-01']['target_label']]);
        $this->assertSame($assets->requirement_l1, $actions['ID-01']['action']);
    }

    public function test_no_actions_when_every_assessed_element_is_advanced(): void
    {
        $assessment = Assessment::factory()->withElements(defaultLevel: 3)->create();

        $this->assertSame([], $this->app->make(ReportService::class)->prioritise($assessment));
    }

    /**
     * FR-30 with the PRD §7.6 bands, a domain with no score (DECISIONS D-58) and the change since the last cycle.
     */
    public function test_risk_profile_categorises_every_domain_and_reports_the_change(): void
    {
        Assessment::factory()
            ->withDomainScores(['GOVERN' => 0.5, 'IDENTIFY' => 1.0, 'PROTECT' => 2.0, 'DETECT' => 2.5, 'RESPOND' => 3.0])
            ->create(['assessment_date' => '2025-07-01']);
        $current = Assessment::factory()
            ->withDomainScores(['GOVERN' => 0.99, 'IDENTIFY' => 1.74, 'PROTECT' => 1.75, 'DETECT' => 2.5, 'RESPOND' => null])
            ->create(['assessment_date' => '2026-01-01']);
        $this->app->make(TrendService::class)->computeTrend($current);

        $profile = collect($this->app->make(ReportService::class)->riskProfile($current->fresh()))->keyBy('domain');

        $this->assertSame(
            ['GOVERN' => 'Critical', 'IDENTIFY' => 'High', 'PROTECT' => 'Moderate', 'DETECT' => 'Acceptable', 'RESPOND' => 'Not assessed', 'RECOVER' => 'Moderate'],
            $profile->map(fn (array $domain): string => $domain['risk'])->all(),
        );
        $this->assertSame(
            ['GOVERN' => 'Improved', 'IDENTIFY' => 'Improved', 'PROTECT' => 'Declined', 'DETECT' => 'No change', 'RESPOND' => null, 'RECOVER' => 'No change'],
            $profile->map(fn (array $domain): ?string => $domain['change'])->all(),
        );
        $this->assertSame('Cybersecurity governance', $profile['GOVERN']['area']);
        $this->assertStringStartsWith('Controls in cybersecurity governance are largely absent.', $profile['GOVERN']['narrative']);
        $this->assertStringStartsWith('Incident response was not assessed in this cycle', $profile['RESPOND']['narrative']);
    }

    public function test_first_cycle_has_no_change_since_last_assessment(): void
    {
        $profile = $this->app->make(ReportService::class)->riskProfile(Assessment::factory()->create());

        $this->assertSame(array_fill(0, 6, null), array_column($profile, 'change'));
    }

    public function test_payload_carries_the_cover_details_status_and_unanswered_elements(): void
    {
        Setting::store(Setting::OrganisationName, 'Malaysia Airports Holdings Berhad');
        Setting::store(Setting::ReportFooter, 'CONFIDENTIAL');
        $assessment = Assessment::factory()
            ->withElements(['PR-05' => null, 'GV-02' => null], defaultLevel: 3)
            ->create(['cycle_label' => 'Cycle 2026 H1', 'assessment_date' => '2026-06-15']);
        $officer = User::factory()->complianceOfficer()->create(['name' => 'Aina Officer']);

        $payload = $this->app->make(ReportService::class)->buildPayload($assessment, $officer, now());

        $this->assertSame(1, $payload['version']);
        $this->assertSame('Malaysia Airports Holdings Berhad', $payload['organisation']);
        $this->assertSame('CONFIDENTIAL', $payload['footer']);
        $this->assertSame(['Cycle 2026 H1', '2026-06-15', 'Advanced'], [$payload['cycle_label'], $payload['assessment_date'], $payload['overall_band']]);
        $this->assertSame('Aina Officer', $payload['generated_by']);
        $this->assertSame(['GV-02', 'PR-05'], array_column($payload['unanswered'], 'code'));
        // Advanced with every domain Acceptable, but unanswered elements block "Compliant" (DECISIONS D-50).
        $this->assertSame('Partially compliant', $payload['compliance_status']);
        $this->assertCount(6, $payload['domains']);
        $this->assertSame([], $payload['actions']);
    }

    public function test_organisation_falls_back_to_the_app_name_when_not_set(): void
    {
        $payload = $this->app->make(ReportService::class)
            ->buildPayload(Assessment::factory()->withElements()->create(), User::factory()->create(), now());

        $this->assertSame(config('app.name'), $payload['organisation']);
    }
}
