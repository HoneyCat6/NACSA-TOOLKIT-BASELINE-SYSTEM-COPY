<?php

namespace Tests\Feature\Assessments;

use App\Enums\Domain;
use App\Models\Assessment;
use App\Services\TrendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_cycle_has_no_trend(): void
    {
        $assessment = Assessment::factory()->create();

        $this->assertNull($this->app->make(TrendService::class)->computeTrend($assessment));
        $this->assertDatabaseCount('assessment_trends', 0);
    }

    public function test_computes_signed_deltas_against_the_previous_cycle(): void
    {
        Assessment::factory()->create(['assessment_date' => '2025-01-15', 'overall_score' => 0.900]);
        $previous = Assessment::factory()->withDomainScores(['GOVERN' => 2.0, 'PROTECT' => 2.5])
            ->create(['assessment_date' => '2025-07-15', 'overall_score' => 0.818]);
        $current = Assessment::factory()->withDomainScores(['GOVERN' => 2.43, 'PROTECT' => 2.0])
            ->create(['assessment_date' => '2026-01-15', 'overall_score' => 0.879]);

        $trend = $this->app->make(TrendService::class)->computeTrend($current);

        $this->assertSame($previous->id, $trend->prev_assessment_id);
        $this->assertSame('0.43', $trend->fresh()->govern_delta);
        $this->assertSame('-0.50', $trend->fresh()->protect_delta);
        $this->assertSame('0.00', $trend->fresh()->detect_delta);
        $this->assertSame('0.061', $trend->fresh()->overall_delta);
    }

    public function test_raises_one_alert_per_declining_domain_only(): void
    {
        Assessment::factory()->withDomainScores(['PROTECT' => 2.5, 'RESPOND' => 3.0, 'GOVERN' => 1.0])
            ->create(['assessment_date' => '2025-07-15']);
        $current = Assessment::factory()->withDomainScores(['PROTECT' => 2.0, 'RESPOND' => 1.0, 'GOVERN' => 2.0])
            ->create(['assessment_date' => '2026-01-15']);

        $service = $this->app->make(TrendService::class);
        $alerts = $service->detectDegradation($service->computeTrend($current));

        $this->assertSame([Domain::Protect, Domain::Respond], $alerts->pluck('domain')->all());
        $this->assertSame(['0.50', '2.00'], $alerts->map(fn ($alert) => $alert->fresh()->score_drop)->all());
        $this->assertFalse($alerts->first()->fresh()->acknowledged);
    }

    public function test_domain_missing_from_either_cycle_has_no_delta_and_no_alert(): void
    {
        Assessment::factory()->withDomainScores(['RESPOND' => 3.0, 'RECOVER' => null])->create(['assessment_date' => '2025-07-15']);
        $current = Assessment::factory()->withDomainScores(['RESPOND' => null, 'RECOVER' => 1.0])->create(['assessment_date' => '2026-01-15']);

        $service = $this->app->make(TrendService::class);
        $trend = $service->computeTrend($current);

        $this->assertNull($trend->respond_delta);
        $this->assertNull($trend->recover_delta);
        $this->assertTrue($service->detectDegradation($trend)->isEmpty());
    }

    public function test_chart_data_flags_domains_that_declined_in_the_latest_cycle(): void
    {
        Assessment::factory()->withDomainScores(['PROTECT' => 2.5])
            ->create(['assessment_date' => '2025-07-15', 'cycle_label' => 'Cycle 2025 H2', 'overall_score' => 0.8]);
        $current = Assessment::factory()->withDomainScores(['PROTECT' => 2.0])
            ->create(['assessment_date' => '2026-01-15', 'cycle_label' => 'Cycle 2026 H1', 'overall_score' => 0.9]);

        $service = $this->app->make(TrendService::class);
        $service->computeTrend($current);

        $chart = $service->chartData(Assessment::query()->with('trend')->chronological()->get());
        $protect = collect($chart['datasets'])->firstWhere('label', 'PROTECT');

        $this->assertSame(['Cycle 2025 H2', 'Cycle 2026 H1'], $chart['labels']);
        $this->assertSame([2.5, 2.0], $protect['data']);
        $this->assertTrue($protect['declining']);
        $this->assertFalse(collect($chart['datasets'])->firstWhere('label', 'GOVERN')['declining']);
        $this->assertSame([0.8, 0.9], $chart['overall']);
    }
}
