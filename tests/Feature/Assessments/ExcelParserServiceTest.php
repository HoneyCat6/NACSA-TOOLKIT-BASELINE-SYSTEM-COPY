<?php

namespace Tests\Feature\Assessments;

use App\DataObjects\ParsedAssessment;
use App\Exceptions\TemplateValidationException;
use App\Services\ExcelParserService;
use Database\Seeders\NcsbElementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\ToolkitWorkbook;
use Tests\TestCase;

class ExcelParserServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(NcsbElementSeeder::class);
    }

    public function test_extracts_all_125_responses_from_a_completed_toolkit(): void
    {
        $parsed = $this->parse(ToolkitWorkbook::allYes()->answerElement('GV-02', ['Yes', 'No', null]));

        $this->assertCount(33, $parsed->responsesByElement);
        $this->assertSame(125, collect($parsed->responsesByElement)->flatten()->count());
        $this->assertSame([true, false, null], $parsed->responsesByElement['GV-02']);
        $this->assertSame([true, true, true, true, true], $parsed->responsesByElement['GV-01']);
        $this->assertSame([], $parsed->unansweredCodes);
        $this->assertSame([], $parsed->warnings);
    }

    public function test_accepts_yes_and_no_in_any_case_with_surrounding_spaces(): void
    {
        $parsed = $this->parse(ToolkitWorkbook::allYes()->answerElement('GV-02', [' yes ', 'YES', 'no']));

        $this->assertSame([true, true, false], $parsed->responsesByElement['GV-02']);
        $this->assertSame([], $parsed->warnings);
    }

    public function test_treats_unrecognised_answers_as_no_with_a_warning(): void
    {
        $parsed = $this->parse(ToolkitWorkbook::allYes()->answerElement('GV-02', ['Yes', 'Maybe', 'No']));

        $this->assertSame([true, false, false], $parsed->responsesByElement['GV-02']);
        $this->assertSame(['Question 7 (row 9): "Maybe" is not Yes or No and was treated as No.'], $parsed->warnings);
    }

    public function test_counts_a_yes_after_a_no_but_warns(): void
    {
        $parsed = $this->parse(ToolkitWorkbook::allYes()->answerElement('GV-01', ['Yes', 'No', 'Yes', 'Yes', null]));

        $this->assertSame([true, false, true, true, null], $parsed->responsesByElement['GV-01']);
        $this->assertCount(1, $parsed->warnings);
        $this->assertStringStartsWith('GV-01 Cyber Security Policy: "Yes" at question 3 (row 5) follows a "No".', $parsed->warnings[0]);
    }

    public function test_defaulted_cells_are_blanks_before_the_first_no(): void
    {
        $parsed = $this->parse(ToolkitWorkbook::allYes()
            ->answerElement('GV-01', ['Yes', null, 'No', null, null])
            ->answerElement('GV-02', [null, null, null])
            ->answerElement('GV-03', ['Yes', null, null, null]));

        // GV-01: one blank before the "No" (the two after it are intended skips); GV-02 is unanswered; GV-03: three.
        $this->assertSame(4, $parsed->defaultedCellCount);
    }

    public function test_fully_blank_element_is_unanswered(): void
    {
        $parsed = $this->parse(ToolkitWorkbook::allYes()->answerElement('RS-01', [null, null, null]));

        $this->assertSame(['RS-01'], $parsed->unansweredCodes);
        $this->assertSame(0, $parsed->defaultedCellCount);
    }

    public function test_blank_toolkit_has_every_element_unanswered(): void
    {
        $parsed = $this->parse(ToolkitWorkbook::blank());

        $this->assertCount(33, $parsed->unansweredCodes);
    }

    public function test_rejects_a_renamed_sheet(): void
    {
        $this->assertRejected(
            ToolkitWorkbook::allYes()->renameSheet('CS Baseline Questionnaires', 'PROTECT'),
            "Sheet 'CS Baseline Questionnaires' missing. Upload the unmodified NCSB v1.4 toolkit.",
        );
    }

    public function test_rejects_an_added_sheet(): void
    {
        $this->assertRejected(
            ToolkitWorkbook::allYes()->addSheet('Notes'),
            "Unexpected sheet 'Notes'. The workbook has been modified from the NCSB v1.4 template.",
        );
    }

    public function test_rejects_a_modified_question(): void
    {
        $this->assertRejected(
            ToolkitWorkbook::allYes()->setCell(ToolkitWorkbook::QuestionnaireSheet, 'G39', 'Is capacity monitored?'),
            'Question 37 (row 39) does not match the NCSB v1.4 template.',
        );
    }

    public function test_rejects_a_moved_element_name(): void
    {
        $this->assertRejected(
            ToolkitWorkbook::allYes()->setCell(ToolkitWorkbook::QuestionnaireSheet, 'E8', 'Objectives'),
            "Element 'Cyber Security Objectives' not found at row 8. The workbook does not match the NCSB v1.4 template.",
        );
    }

    public function test_rejects_a_file_that_is_not_a_workbook(): void
    {
        $file = UploadedFile::fake()->createWithContent('toolkit.xlsx', 'not a spreadsheet');

        $this->expectExceptionObject(new TemplateValidationException(ExcelParserService::UnreadableMessage));

        $this->app->make(ExcelParserService::class)->parse($file->getRealPath());
    }

    private function parse(ToolkitWorkbook $workbook): ParsedAssessment
    {
        return $this->app->make(ExcelParserService::class)->parse($workbook->toUploadedFile()->getRealPath());
    }

    private function assertRejected(ToolkitWorkbook $workbook, string $message): void
    {
        try {
            $this->parse($workbook);
        } catch (TemplateValidationException $exception) {
            $this->assertSame($message, $exception->getMessage());

            return;
        }

        $this->fail('The workbook was not rejected.');
    }
}
