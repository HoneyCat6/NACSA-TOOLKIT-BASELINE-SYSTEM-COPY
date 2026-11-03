<?php

namespace Tests\Feature;

use App\Enums\Domain;
use App\Models\Assessment;
use App\Models\AssessmentElement;
use App\Models\DegradationAlert;
use App\Models\User;
use App\Services\ExcelParserService;
use App\Services\ScoringService;
use Database\Seeders\ForecastAssessmentSeeder;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ForecastAssessmentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_three_complete_assessments_per_year_with_consistent_scores_and_trends(): void
    {
        User::factory()->complianceOfficer()->create();

        $this->seed([NcsbElementSeeder::class, ForecastAssessmentSeeder::class]);

        $assessments = Assessment::query()->with('elements.ncsbElement', 'trend')->chronological()->get();
        $this->assertSame([
            '2024_1_Assessment', '2024_2_Assessment', '2024_3_Assessment',
            '2025_1_Assessment', '2025_2_Assessment', '2025_3_Assessment',
            '2026_1_Assessment', '2026_2_Assessment', '2026_3_Assessment',
        ], $assessments->pluck('cycle_label')->all());
        $this->assertSame([
            '2024-01-15', '2024-05-15', '2024-09-15',
            '2025-01-15', '2025-05-15', '2025-09-15',
            '2026-01-15', '2026-05-15', '2026-09-15',
        ], $assessments->map(fn (Assessment $assessment): string => $assessment->assessment_date->toDateString())->all());
        $this->assertDatabaseCount('assessment_elements', 297);
        $this->assertDatabaseCount('assessment_trends', 8);
        $this->assertNull($assessments->first()->trend);

        foreach ($assessments as $index => $assessment) {
            $rawAnswers = $assessment->elements->mapWithKeys(fn (AssessmentElement $element): array => [
                $element->element_code => array_map(fn (?bool $answer): ?string => $answer === null ? null : ($answer ? 'Yes' : 'No'), $element->responses),
            ])->all();
            $references = $assessment->elements->map->ncsbElement;
            $scored = app(ScoringService::class)->score(app(ExcelParserService::class)->interpretAnswers($references, $rawAnswers));

            $this->assertSame(0, $assessment->unanswered_count);
            $this->assertSame(0, $assessment->defaulted_cell_count);
            $this->assertNull($assessment->parse_warnings);
            $this->assertSame($scored->overallScore, (float) $assessment->overall_score);
            $this->assertSame($scored->overallBand, $assessment->overall_band);

            foreach (Domain::cases() as $domain) {
                $this->assertSame($scored->domainScores[$domain->value], (float) $assessment->{$domain->scoreColumn()});
            }

            if ($index > 0) {
                $this->assertSame($assessments[$index - 1]->id, $assessment->trend->prev_assessment_id);
            }
        }

        $latest = $assessments->last();
        $this->assertSame('2.29', $latest->govern_score);
        $this->assertSame('1.58', $latest->protect_score);
        $this->assertSame('2.00', $latest->recover_score);
        $this->assertSame('0.15', $latest->trend->govern_delta);
        $this->assertSame('-0.10', $latest->trend->protect_delta);
        $this->assertSame('0.00', $latest->trend->recover_delta);
        $this->assertDatabaseHas('degradation_alerts', ['assessment_trend_id' => $latest->trend->id, 'domain' => Domain::Protect->value, 'ncsb_element_id' => null]);
    }

    public function test_running_twice_preserves_the_same_assessments_trends_and_alerts(): void
    {
        User::factory()->complianceOfficer()->create();
        $this->seed([NcsbElementSeeder::class, ForecastAssessmentSeeder::class]);
        $assessmentIds = Assessment::query()->chronological()->pluck('id')->all();
        $alertIds = DegradationAlert::query()->orderBy('id')->pluck('id')->all();

        $this->seed(ForecastAssessmentSeeder::class);

        $this->assertSame($assessmentIds, Assessment::query()->chronological()->pluck('id')->all());
        $this->assertSame($alertIds, DegradationAlert::query()->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('assessment_trends', 8);
    }

    public function test_existing_assessment_history_is_preserved(): void
    {
        $assessment = Assessment::factory()->create();

        try {
            $this->seed(ForecastAssessmentSeeder::class);
            $this->fail('Expected existing history to prevent forecast demo seeding.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forecast demo requires an empty assessment history; existing assessments were not changed.', $exception->getMessage());
        }

        $this->assertModelExists($assessment);
        $this->assertDatabaseCount('assessments', 1);
        $this->assertDatabaseCount('assessment_trends', 0);
    }

    public function test_requires_an_active_compliance_officer(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No active Compliance Officer.');

        $this->seed(ForecastAssessmentSeeder::class);
    }

    public function test_requires_reference_elements(): void
    {
        User::factory()->complianceOfficer()->create();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Seed the 33 NCSB reference elements');

        $this->seed(ForecastAssessmentSeeder::class);
    }
}
