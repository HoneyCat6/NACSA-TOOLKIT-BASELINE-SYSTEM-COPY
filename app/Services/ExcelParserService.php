<?php

namespace App\Services;

use App\DataObjects\ParsedAssessment;
use App\Exceptions\TemplateValidationException;
use App\Models\NcsbElement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Validates an uploaded workbook against the NCSB v1.4 template and extracts the 125 raw responses (PRD §7.2).
 *
 * Score cells inside the workbook are never read — everything is recomputed by ScoringService.
 */
class ExcelParserService
{
    public const UnreadableMessage = 'The file could not be read. Confirm it is an unmodified .xlsx toolkit.';

    /**
     * @throws TemplateValidationException Nothing should be persisted when this is thrown.
     */
    public function parse(string $path): ParsedAssessment
    {
        $elements = NcsbElement::query()->orderBy('sort_order')->get();

        if ($elements->count() !== config('ncsb.element_count')) {
            throw new RuntimeException('Reference data not seeded. Run `php artisan db:seed --class=NcsbElementSeeder`.');
        }

        $questionnaire = $this->loadQuestionnaire($path);
        $this->assertQuestionsMatch($questionnaire, $elements);
        $this->assertElementNamesMatch($questionnaire, $elements);

        return $this->extract($questionnaire, $elements);
    }

    private function loadQuestionnaire(string $path): Worksheet
    {
        $reader = new Xlsx;

        try {
            $sheetNames = $reader->canRead($path) ? $reader->listWorksheetNames($path) : null;
        } catch (Throwable $exception) {
            throw new TemplateValidationException(self::UnreadableMessage, previous: $exception);
        }

        if ($sheetNames === null) {
            throw new TemplateValidationException(self::UnreadableMessage);
        }

        $this->assertSheetsMatch($sheetNames);

        try {
            $reader->setReadDataOnly(true);
            $reader->setLoadSheetsOnly([config('ncsb.questionnaire.sheet')]);

            return $reader->load($path)->getSheetByNameOrThrow(config('ncsb.questionnaire.sheet'));
        } catch (Throwable $exception) {
            throw new TemplateValidationException(self::UnreadableMessage, previous: $exception);
        }
    }

    /**
     * D-10 (a): all six sheet names must match exactly, with nothing added.
     *
     * @param  list<string>  $sheetNames
     */
    private function assertSheetsMatch(array $sheetNames): void
    {
        foreach (config('ncsb.sheets') as $expected) {
            if (! in_array($expected, $sheetNames, true)) {
                throw new TemplateValidationException("Sheet '{$expected}' missing. Upload the unmodified NCSB v1.4 toolkit.");
            }
        }

        foreach ($sheetNames as $actual) {
            if (! in_array($actual, config('ncsb.sheets'), true)) {
                throw new TemplateValidationException("Unexpected sheet '{$actual}'. The workbook has been modified from the NCSB v1.4 template.");
            }
        }
    }

    /**
     * D-10 (b, c): the 125 question texts must sit at their seeded rows.
     *
     * @param  Collection<int, NcsbElement>  $elements
     */
    private function assertQuestionsMatch(Worksheet $questionnaire, Collection $elements): void
    {
        $column = config('ncsb.questionnaire.question_column');

        foreach ($elements as $element) {
            foreach ($element->questions as $offset => $expected) {
                $row = $element->first_row + $offset;

                if ($this->normaliseText($questionnaire->getCell($column.$row)->getValue()) !== $this->normaliseText($expected)) {
                    throw new TemplateValidationException(
                        "Question {$this->questionNumber($row)} (row {$row}) does not match the NCSB v1.4 template.",
                    );
                }
            }
        }
    }

    /**
     * D-10 (d): each element name must appear at its start row.
     *
     * @param  Collection<int, NcsbElement>  $elements
     */
    private function assertElementNamesMatch(Worksheet $questionnaire, Collection $elements): void
    {
        $column = config('ncsb.questionnaire.element_column');

        foreach ($elements as $element) {
            $actual = $questionnaire->getCell($column.$element->first_row)->getValue();

            if ($this->normaliseText($actual) !== $this->normaliseText($element->name)) {
                throw new TemplateValidationException(
                    "Element '{$element->name}' not found at row {$element->first_row}. The workbook does not match the NCSB v1.4 template.",
                );
            }
        }
    }

    /**
     * @param  Collection<int, NcsbElement>  $elements
     */
    private function extract(Worksheet $questionnaire, Collection $elements): ParsedAssessment
    {
        $column = config('ncsb.questionnaire.response_column');
        $rawAnswers = [];

        foreach ($elements as $element) {
            foreach (range($element->first_row, $element->last_row) as $row) {
                $cell = $questionnaire->getCell($column.$row);
                $rawAnswers[$element->code][] = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
            }
        }

        return $this->interpretAnswers($elements, $rawAnswers);
    }

    /**
     * Apply the answer rules (D-12 – D-14, FR-13) to raw response cell values.
     *
     * @param  Collection<int, NcsbElement>  $elements  in questionnaire order
     * @param  array<string, list<mixed>>  $rawAnswers  element code => cell values in question order
     */
    public function interpretAnswers(Collection $elements, array $rawAnswers): ParsedAssessment
    {
        $responsesByElement = [];
        $unansweredCodes = [];
        $defaultedCellCount = 0;
        $warnings = [];

        foreach ($elements as $element) {
            $answers = [];
            $hasNo = false;
            $hasAnswer = false;
            $blanksBeforeFirstNo = 0;
            $warnedYesAfterNo = false;

            foreach (range($element->first_row, $element->last_row) as $offset => $row) {
                $raw = $this->normaliseText($rawAnswers[$element->code][$offset] ?? null);
                $answer = $this->interpret($raw);

                if ($answer === null && $raw !== '') {
                    $warnings[] = "Question {$this->questionNumber($row)} (row {$row}): \"{$raw}\" is not Yes or No and was treated as No.";
                    $answer = false;
                }

                if ($answer === null) {
                    $blanksBeforeFirstNo += $hasNo ? 0 : 1;
                } else {
                    $hasAnswer = true;

                    // D-13: still counted, as the toolkit's COUNTIF does.
                    if ($answer && $hasNo && ! $warnedYesAfterNo) {
                        $warnings[] = "{$element->code} {$element->name}: \"Yes\" at question {$this->questionNumber($row)} (row {$row}) follows a \"No\". It was counted, but the toolkit expects the remaining questions to be skipped after a \"No\".";
                        $warnedYesAfterNo = true;
                    }

                    $hasNo = $hasNo || ! $answer;
                }

                $answers[] = $answer;
            }

            $responsesByElement[$element->code] = $answers;

            // D-14 / FR-13: a fully blank element is Unanswered and its blanks are not counted as defaulted.
            if ($hasAnswer) {
                $defaultedCellCount += $blanksBeforeFirstNo;
            } else {
                $unansweredCodes[] = $element->code;
            }
        }

        return new ParsedAssessment($responsesByElement, $unansweredCodes, $defaultedCellCount, $warnings);
    }

    /**
     * D-12: "Yes" / "No", case-insensitive and trimmed. Returns null for blank or unrecognised values.
     */
    private function interpret(string $value): ?bool
    {
        return match (Str::lower($value)) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    private function normaliseText(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function questionNumber(int $row): int
    {
        return $row - config('ncsb.questionnaire.first_row') + 1;
    }
}
