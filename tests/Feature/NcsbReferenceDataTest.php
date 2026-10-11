<?php

namespace Tests\Feature;

use PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Proves the seeded reference data matches the official NCSB v1.4 toolkit (PRD Appendix A).
 */
class NcsbReferenceDataTest extends TestCase
{
    private static ?Spreadsheet $toolkit = null;

    /**
     * @var array{source: string, elements: list<array<string, mixed>>}|null
     */
    private static ?array $referenceData = null;

    public function test_workbook_has_the_expected_sheets(): void
    {
        $this->assertSame(config('ncsb.sheets'), $this->toolkit()->getSheetNames());
    }

    public function test_elements_match_the_questionnaire_sheet(): void
    {
        $questionnaire = $this->toolkit()->getSheetByName(config('ncsb.questionnaire.sheet'));

        foreach ($this->elements() as $element) {
            $firstRow = $element['first_row'];

            $this->assertSame($element['domain'], $this->normalise($questionnaire->getCell("C{$firstRow}")->getValue()), "{$element['code']} domain");
            $this->assertSame($element['category'], $this->normalise($questionnaire->getCell("D{$firstRow}")->getValue()), "{$element['code']} category");
            $this->assertSame($element['name'], $this->normalise($questionnaire->getCell("E{$firstRow}")->getValue()), "{$element['code']} name");

            foreach ($element['questions'] as $offset => $question) {
                $row = $firstRow + $offset;
                $this->assertSame($this->normalise($question), $this->normalise($questionnaire->getCell("G{$row}")->getValue()), "{$element['code']} question row {$row}");
            }
        }
    }

    public function test_requirement_texts_match_the_model_sheet(): void
    {
        $model = $this->toolkit()->getSheetByName('CS Baseline Model');

        foreach ($this->elements() as $element) {
            $row = $element['sort_order'] + 3;

            foreach (['J' => 'requirement_l1', 'K' => 'requirement_l2', 'L' => 'requirement_l3'] as $column => $field) {
                $this->assertSame(
                    $this->normalise($element[$field]),
                    $this->normalise($model->getCell("{$column}{$row}")->getValue()),
                    "{$element['code']} {$field}",
                );
            }
        }
    }

    public function test_ranges_and_thresholds_match_the_scoring_dashboard_formulas(): void
    {
        $dashboard = $this->toolkit()->getSheetByName('CS Baseline Scoring Dashboard');
        $calculation = Calculation::getInstance($this->toolkit());

        foreach ($this->elements() as $element) {
            $row = $element['sort_order'] + 2;

            $this->assertSame($element['question_count'], (int) $dashboard->getCell("C{$row}")->getValue());
            $this->assertStringContainsString(
                "!H{$element['first_row']}:H{$element['last_row']}",
                (string) $dashboard->getCell("D{$row}")->getValue(),
                "{$element['code']} response range",
            );

            // DECISIONS D-15: the level formula in column E is authoritative — evaluate it for every Yes-count.
            $levelByYesCount = [];
            foreach (range(0, $element['question_count']) as $yesCount) {
                $dashboard->setCellValue("D{$row}", $yesCount);
                $calculation->clearCalculationCache();
                $levelByYesCount[$yesCount] = (int) $dashboard->getCell("E{$row}")->getCalculatedValue();
            }

            foreach ([1, 2, 3] as $level) {
                $minimumYesCount = collect($levelByYesCount)->filter(fn (int $reached): bool => $reached >= $level)->keys()->first();
                $this->assertSame($minimumYesCount, $element["threshold_l{$level}"], "{$element['code']} threshold L{$level}");
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function elements(): array
    {
        self::$referenceData ??= json_decode(file_get_contents(config('ncsb.reference_data')), true, flags: JSON_THROW_ON_ERROR);

        return self::$referenceData['elements'];
    }

    private function toolkit(): Spreadsheet
    {
        if (self::$toolkit === null) {
            $this->elements();
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            self::$toolkit = $reader->load(base_path(self::$referenceData['source']));
        }

        return self::$toolkit;
    }

    private function normalise(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
