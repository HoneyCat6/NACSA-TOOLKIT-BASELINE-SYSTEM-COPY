<?php

namespace Tests\Feature\Assessments;

use App\DataObjects\ParsedAssessment;
use App\DataObjects\ScoredAssessment;
use App\Enums\Band;
use App\Enums\ElementStatus;
use App\Enums\MaturityLevel;
use App\Services\ExcelParserService;
use App\Services\ScoringService;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ToolkitWorkbook;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NcsbElementSeeder::class);
    }

    /**
     * GV-01 has 5 questions with thresholds L1 = 2, L2 = 3, L3 = 5.
     *
     * @return array<string, array{int, MaturityLevel}>
     */
    public static function cyberSecurityPolicyBoundaries(): array
    {
        return [
            '0 Yes' => [0, MaturityLevel::Initial],
            '1 Yes' => [1, MaturityLevel::Initial],
            '2 Yes' => [2, MaturityLevel::Basic],
            '3 Yes' => [3, MaturityLevel::Intermediate],
            '4 Yes' => [4, MaturityLevel::Intermediate],
            '5 Yes' => [5, MaturityLevel::Advanced],
        ];
    }

    #[DataProvider('cyberSecurityPolicyBoundaries')]
    public function test_element_level_follows_the_yes_count_thresholds(int $yesCount, MaturityLevel $expected): void
    {
        $scored = $this->score(['GV-01' => [...array_fill(0, $yesCount, true), ...array_fill(0, 5 - $yesCount, false)]]);

        $this->assertSame($expected, $scored->elements['GV-01']->level);
        $this->assertSame($yesCount, $scored->elements['GV-01']->yesCount);
    }

    public function test_blank_answers_count_as_no(): void
    {
        $scored = $this->score(['GV-01' => [true, true, null, null, null]]);

        $this->assertSame(2, $scored->elements['GV-01']->yesCount);
        $this->assertSame(MaturityLevel::Basic, $scored->elements['GV-01']->level);
    }

    public function test_unanswered_element_has_no_level_and_is_left_out_of_its_domain_score(): void
    {
        // RESPOND has one element, so leaving it unanswered leaves the domain without a score.
        $scored = $this->score(['RS-01' => [null, null, null]], unanswered: ['RS-01']);

        $this->assertNull($scored->elements['RS-01']->level);
        $this->assertSame(ElementStatus::Unanswered, $scored->elements['RS-01']->status);
        $this->assertNull($scored->domainScores['RESPOND']);
        $this->assertSame(1, $scored->unansweredCount);
    }

    public function test_overall_score_is_the_share_of_elements_with_any_yes_out_of_33(): void
    {
        // Three elements with no "Yes" (one of them unanswered) → 30 of 33 implemented, the divisor stays 33.
        $scored = $this->score([
            'GV-01' => [false, false, false, false, false],
            'GV-02' => [false, null, null],
            'RS-01' => [null, null, null],
        ], unanswered: ['RS-01']);

        $this->assertSame(round(30 / 33, 3), $scored->overallScore);
        $this->assertSame(Band::Advanced, $scored->overallBand);
    }

    public function test_domain_score_is_the_mean_element_level_rounded_to_two_decimals(): void
    {
        // IDENTIFY: ID-01 at Level 3, ID-02 (thresholds 2/3/5) at Level 1 → mean 2.00.
        // DETECT: DE-01 at Level 3, DE-02 (thresholds 1/2/3) at Level 2 → mean 2.50.
        // GOVERN: six elements at Level 3 and GV-01 at Level 0 → 18 / 7 = 2.571… → 2.57.
        $scored = $this->score([
            'ID-02' => [true, true, false, false, false],
            'DE-02' => [true, true, false],
            'GV-01' => [false, false, false, false, false],
        ]);

        $this->assertSame(2.0, $scored->domainScores['IDENTIFY']);
        $this->assertSame(2.5, $scored->domainScores['DETECT']);
        $this->assertSame(2.57, $scored->domainScores['GOVERN']);
        $this->assertSame(3.0, $scored->domainScores['RECOVER']);
    }

    public function test_scores_match_the_toolkit_scoring_dashboard_formulas(): void
    {
        $workbook = ToolkitWorkbook::blank();

        // A deterministic mix: every Yes-count, blanks, Yes-after-No and case variations — never a fully blank element,
        // which the toolkit scores 0 but this system marks Unanswered (FR-13).
        foreach (array_values(array_keys(ToolkitWorkbook::elements())) as $index => $code) {
            $questionCount = ToolkitWorkbook::elements()[$code]['question_count'];
            $yesCount = $index % ($questionCount + 1);
            $yes = $index % 3 === 0 ? 'YES' : 'Yes';

            $answers = match ($index % 4) {
                0 => [...array_fill(0, $yesCount, $yes), ...array_fill(0, $questionCount - $yesCount, 'No')],
                1 => [...array_fill(0, $yesCount, $yes), 'no'],
                2 => ['No', ...array_fill(0, $yesCount, $yes)],
                default => [...array_fill(0, $yesCount, $yes), 'No', null],
            };

            $workbook->answerElement($code, array_slice($answers, 0, $questionCount));
        }

        $file = $workbook->toUploadedFile();
        $scored = $this->app->make(ScoringService::class)->score($this->app->make(ExcelParserService::class)->parse($file->getRealPath()));

        $dashboard = $workbook->toSpreadsheet()->getSheetByNameOrThrow('CS Baseline Scoring Dashboard');

        foreach (array_values($scored->elements) as $index => $element) {
            $row = $index + 3;
            $this->assertSame((int) $dashboard->getCell("D{$row}")->getCalculatedValue(), $element->yesCount, "{$element->code} Yes-count");
            $this->assertSame((int) $dashboard->getCell("E{$row}")->getCalculatedValue(), $element->level->value, "{$element->code} level");
        }

        $this->assertSame(round((float) $dashboard->getCell('J2')->getCalculatedValue(), 3), $scored->overallScore);
        $this->assertSame((string) $dashboard->getCell('K2')->getCalculatedValue(), $scored->overallBand->value);
    }

    /**
     * Every element answered "Yes" throughout, except the overrides.
     *
     * @param  array<string, list<?bool>>  $overrides
     * @param  list<string>  $unanswered
     */
    private function score(array $overrides, array $unanswered = []): ScoredAssessment
    {
        $responses = [];
        foreach (ToolkitWorkbook::elements() as $code => $element) {
            $responses[$code] = $overrides[$code] ?? array_fill(0, $element['question_count'], true);
        }

        return $this->app->make(ScoringService::class)->score(new ParsedAssessment($responses, $unanswered, 0, []));
    }
}
