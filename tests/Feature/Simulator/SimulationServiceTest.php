<?php

namespace Tests\Feature\Simulator;

use App\Enums\Band;
use App\Models\Assessment;
use App\Services\SimulationService;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PRD §7.7 projection rules with DECISIONS D-21, D-22 and D-24.
 */
class SimulationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NcsbElementSeeder::class);
    }

    public function test_no_changes_projects_the_baseline_exactly(): void
    {
        $baseline = Assessment::factory()->withElements(['RS-01' => 0, 'PR-09' => 3, 'GV-01' => null])->create();

        $result = app(SimulationService::class)->project($baseline, []);

        $this->assertSame(['GOVERN' => 2.0, 'IDENTIFY' => 2.0, 'PROTECT' => 2.05, 'DETECT' => 2.0, 'RESPOND' => 0.0, 'RECOVER' => 2.0], $result->projectedDomainScores);
        $this->assertSame(['GOVERN' => 0.0, 'IDENTIFY' => 0.0, 'PROTECT' => 0.0, 'DETECT' => 0.0, 'RESPOND' => 0.0, 'RECOVER' => 0.0], $result->domainDeltas);
        $this->assertSame(0.939, $result->projectedOverallScore);
        $this->assertSame(Band::Advanced, $result->projectedBand);
        $this->assertSame(0.0, $result->overallDelta);
        $this->assertSame(1, $result->elementsAtTarget);
        $this->assertSame([], $result->overrides);
    }

    public function test_overrides_recompute_domain_scores_overall_score_and_deltas(): void
    {
        $baseline = Assessment::factory()->withElements(['RS-01' => 0, 'DE-01' => 0, 'DE-02' => 0], defaultLevel: 1)->create();

        $result = app(SimulationService::class)->project($baseline, ['RS-01' => 3, 'DE-01' => 2, 'PR-01' => 3]);

        $this->assertSame(3.0, $result->projectedDomainScores['RESPOND']);
        $this->assertSame(3.0, $result->domainDeltas['RESPOND']);
        $this->assertSame(1.0, $result->projectedDomainScores['DETECT']);
        $this->assertSame(1.0, $result->domainDeltas['DETECT']);
        $this->assertSame(1.11, $result->projectedDomainScores['PROTECT']);
        $this->assertSame(0.11, $result->domainDeltas['PROTECT']);
        $this->assertSame(0.0, $result->domainDeltas['GOVERN']);

        $this->assertSame(0.970, $result->projectedOverallScore);
        $this->assertSame(Band::Advanced, $result->projectedBand);
        $this->assertSame(0.061, $result->overallDelta);
        $this->assertSame(2, $result->elementsAtTarget);
    }

    public function test_untouched_element_with_a_yes_below_level_1_stays_implemented(): void
    {
        $baseline = Assessment::factory()->withElements()->create(['overall_score' => 1.0, 'overall_band' => Band::Advanced]);
        // GV-01 needs 2 "Yes" for Level 1: one "Yes" is Level 0 but implemented (D-20, D-21).
        $baseline->elements()->where('element_code', 'GV-01')->update(['yes_count' => 1, 'maturity_level' => 0]);

        $result = app(SimulationService::class)->project($baseline, ['PR-09' => 3]);

        $this->assertSame(1.0, $result->projectedOverallScore);
        $this->assertSame(0.0, $result->overallDelta);
    }

    public function test_moved_element_counts_as_implemented_only_at_level_1_or_above(): void
    {
        $baseline = Assessment::factory()->withElements()->create();

        $result = app(SimulationService::class)->project($baseline, ['PR-09' => 0, 'PR-10' => 1]);

        $this->assertSame(0.970, $result->projectedOverallScore);
        $this->assertSame(Band::Advanced, $result->projectedBand);
        $this->assertSame(-0.03, $result->overallDelta);
    }

    public function test_untouched_unanswered_element_is_left_out_of_its_domain_until_moved(): void
    {
        $baseline = Assessment::factory()->withElements(['GV-01' => null])->create();
        $simulator = app(SimulationService::class);

        $untouched = $simulator->project($baseline, []);
        $moved = $simulator->project($baseline, ['GV-01' => 0]);

        $this->assertSame(2.0, $untouched->projectedDomainScores['GOVERN']);
        $this->assertSame(1.71, $moved->projectedDomainScores['GOVERN']);
        $this->assertSame(-0.29, $moved->domainDeltas['GOVERN']);
        $this->assertSame(['GV-01' => 0], $moved->overrides);
        $this->assertSame(0.970, $moved->projectedOverallScore);
    }

    public function test_domain_with_no_baseline_score_has_no_delta(): void
    {
        $baseline = Assessment::factory()->withElements(['RS-01' => null])->create();
        $simulator = app(SimulationService::class);

        $untouched = $simulator->project($baseline, []);
        $moved = $simulator->project($baseline, ['RS-01' => 2]);

        $this->assertNull($untouched->projectedDomainScores['RESPOND']);
        $this->assertNull($untouched->domainDeltas['RESPOND']);
        $this->assertSame(2.0, $moved->projectedDomainScores['RESPOND']);
        $this->assertNull($moved->domainDeltas['RESPOND']);
    }

    public function test_overrides_at_the_baseline_level_unknown_codes_and_out_of_range_levels_are_normalised(): void
    {
        $baseline = Assessment::factory()->withElements(['PR-09' => 1])->create();

        $result = app(SimulationService::class)->project($baseline, ['GV-01' => 2, 'XX-99' => 3, 'PR-09' => 7, 'PR-10' => -2]);

        $this->assertSame(['PR-09' => 3, 'PR-10' => 0], $result->overrides);
    }
}
