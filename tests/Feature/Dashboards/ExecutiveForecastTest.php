<?php

namespace Tests\Feature\Dashboards;

use App\Enums\RiskCategory;
use App\Models\Assessment;
use App\Models\User;
use Database\Seeders\ForecastAssessmentSeeder;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExecutiveForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_mock_history_projects_the_latest_three_cycles_to_january_2027(): void
    {
        User::factory()->complianceOfficer()->create();
        $this->seed([NcsbElementSeeder::class, ForecastAssessmentSeeder::class]);

        $response = $this->signIn(User::factory()->executiveReviewer()->create())->get('/dashboard');

        $response->assertOk()
            ->assertSeeText('Next assessment outlook')
            ->assertSeeText('Current vs next assessment outlook')
            ->assertSee('id="executive-forecast-chart"', false)
            ->assertSeeText('15 Jan 2027')
            ->assertSeeText('2.44 / 3')
            ->assertSeeText('1.50 / 3')
            ->assertSeeText('Assumes recent trends continue')
            ->assertSeeText('2026_1_Assessment')
            ->assertSeeText('2026_2_Assessment')
            ->assertDontSeeText('2025_3_Assessment');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $chart = json_decode($document->getElementById('executive-forecast-data')->textContent, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('15 Sep 2026', $chart['currentDate']);
        $this->assertSame('15 Jan 2027', $chart['forecastDate']);
        $this->assertCount(6, $chart['rows']);
        $this->assertSame(2.44, $chart['rows'][0]['estimate']);
        $this->assertSame('Critical', $chart['rows'][4]['estimatedRisk']);
        $this->assertSame('#dc3545', $chart['rows'][4]['colour']);
        $forecast = $response->viewData('forecast');
        $this->assertNull($forecast['unavailable']);
        $this->assertSame(2.44, $forecast['rows'][0]['estimate']);
        $this->assertSame(1.5, $forecast['rows'][2]['estimate']);
        $this->assertSame(RiskCategory::High, $forecast['rows'][2]['estimated_risk']);
        $this->assertSame(2, $forecast['improving']);
        $this->assertSame(3, $forecast['declining']);
        $this->assertSame(1, $forecast['stable']);
        $this->assertDatabaseCount('assessments', 9);
        $this->assertDatabaseCount('assessment_trends', 8);
    }

    public function test_estimates_are_bounded_and_zero_is_a_valid_score(): void
    {
        foreach (['2026-01-15', '2026-05-15', '2026-09-15'] as $index => $date) {
            Assessment::factory()->withDomainScores([
                'GOVERN' => [2.0, 2.5, 3.0][$index],
                'PROTECT' => [1.0, 0.5, 0.0][$index],
                'DETECT' => [2.2, 2.0, 1.8][$index],
                'RECOVER' => [1.9, 2.2, 2.4][$index],
            ])->create(['assessment_date' => $date]);
        }

        $response = $this->signIn(User::factory()->executiveReviewer()->create())->get('/dashboard');

        $response->assertOk()
            ->assertSeeText('May move from Moderate to High risk')
            ->assertSeeText('May move from Moderate to Acceptable risk');
        $rows = $response->viewData('forecast')['rows'];
        $this->assertSame(3.0, $rows[0]['estimate']);
        $this->assertSame(0.0, $rows[2]['estimate']);
        $this->assertSame('Stable', $rows[2]['direction']);
        $this->assertSame(1.6, $rows[3]['estimate']);
        $this->assertSame(RiskCategory::High, $rows[3]['estimated_risk']);
        $this->assertSame(2.65, $rows[5]['estimate']);
    }

    public function test_irregular_assessment_intervals_are_normalised_by_elapsed_days(): void
    {
        Assessment::factory()->withDomainScores(['GOVERN' => 1.0])->create(['assessment_date' => '2026-01-01']);
        Assessment::factory()->withDomainScores(['GOVERN' => 1.1])->create(['assessment_date' => '2026-02-01']);
        Assessment::factory()->withDomainScores(['GOVERN' => 1.6])->create(['assessment_date' => '2026-09-01']);

        $response = $this->signIn(User::factory()->executiveReviewer()->create())->get('/dashboard');

        $response->assertOk()->assertSeeText('1 Jan 2027');
        $this->assertSame(1.9, $response->viewData('forecast')['rows'][0]['estimate']);
    }

    public function test_a_missing_domain_score_withholds_only_that_domains_estimate(): void
    {
        foreach (['2026-01-15', '2026-05-15', '2026-09-15'] as $index => $date) {
            Assessment::factory()->withDomainScores(['DETECT' => $index === 1 ? null : 2.0])->create(['assessment_date' => $date]);
        }

        $response = $this->signIn(User::factory()->executiveReviewer()->create())->get('/dashboard');

        $response->assertOk()->assertSeeText('Insufficient comparable scores');
        $forecast = $response->viewData('forecast');
        $this->assertNull($forecast['rows'][3]['estimate']);
        $this->assertNull($forecast['rows'][3]['estimated_risk']);
        $this->assertSame(2.0, $forecast['rows'][0]['estimate']);
        $this->assertSame(5, $forecast['stable']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('unreliableResponses')]
    public function test_incomplete_or_flagged_recent_assessments_withhold_the_forecast(array $attributes): void
    {
        Assessment::factory()->create(['assessment_date' => '2025-09-15']);
        Assessment::factory()->create(['assessment_date' => '2026-01-15']);
        Assessment::factory()->create(['assessment_date' => '2026-05-15', ...$attributes]);
        Assessment::factory()->create(['assessment_date' => '2026-09-15']);

        $response = $this->signIn(User::factory()->executiveReviewer()->create())->get('/dashboard');

        $response->assertOk()
            ->assertSeeText('Forecast not available yet')
            ->assertDontSee('id="executive-forecast-chart"', false)
            ->assertSeeText('unanswered or flagged responses');
        $this->assertSame([], $response->viewData('forecast')['rows']);
    }

    public static function unreliableResponses(): array
    {
        return [
            'unanswered controls' => [['unanswered_count' => 1]],
            'defaulted responses' => [['defaulted_cell_count' => 1]],
            'parse warnings' => [['parse_warnings' => ['Review this response']]],
        ];
    }

    public function test_two_assessments_show_the_minimum_history_message(): void
    {
        Assessment::factory()->create(['assessment_date' => '2026-05-15']);
        Assessment::factory()->create(['assessment_date' => '2026-09-15']);

        $this->signIn(User::factory()->executiveReviewer()->create())->get('/dashboard')
            ->assertOk()
            ->assertSeeText('At least three assessment cycles are needed');
    }

    public function test_duplicate_assessment_dates_withhold_the_forecast(): void
    {
        Assessment::factory()->create(['assessment_date' => '2026-05-15']);
        Assessment::factory()->create(['assessment_date' => '2026-05-15']);
        Assessment::factory()->create(['assessment_date' => '2026-09-15']);

        $this->signIn(User::factory()->executiveReviewer()->create())->get('/dashboard')
            ->assertOk()
            ->assertSeeText('distinct, increasing assessment dates');
    }

    public function test_four_month_horizon_does_not_overflow_the_target_month(): void
    {
        Assessment::factory()->create(['assessment_date' => '2025-10-31']);
        Assessment::factory()->create(['assessment_date' => '2026-02-28']);
        Assessment::factory()->create(['assessment_date' => '2026-10-31']);

        $this->signIn(User::factory()->executiveReviewer()->create())->get('/dashboard')
            ->assertOk()
            ->assertSeeText('28 Feb 2027');
    }

    public function test_officers_do_not_see_the_executive_forecast_panel(): void
    {
        $this->signIn(User::factory()->complianceOfficer()->create())->get('/dashboard')
            ->assertOk()
            ->assertDontSeeText('Next assessment outlook');
    }
}
