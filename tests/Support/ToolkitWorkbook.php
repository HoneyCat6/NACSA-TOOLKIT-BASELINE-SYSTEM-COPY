<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use RuntimeException;
use ZipArchive;

/**
 * Filled-in copies of the official blank NCSB v1.4 toolkit, for tests — no real assessment data is committed.
 *
 * Answers are typed straight into the workbook's sheet XML, exactly as Excel stores a typed value, so every
 * fixture is the official file plus answers. (Rebuilding the workbook with PhpSpreadsheet takes seconds per file.)
 */
class ToolkitWorkbook
{
    public const MimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public const QuestionnaireSheet = 'CS Baseline Questionnaires';

    /**
     * @var array<string, array{first_row: int, last_row: int, question_count: int}>|null
     */
    private static ?array $elements = null;

    /**
     * @var array<string, array<string, ?string>> sheet name => cell reference => typed value (null = blank)
     */
    private array $cells = [];

    /**
     * @var array<string, string> original sheet name => new name
     */
    private array $renamedSheets = [];

    /**
     * @var list<string>
     */
    private array $addedSheets = [];

    /**
     * The official template, every response blank.
     */
    public static function blank(): self
    {
        return new self;
    }

    /**
     * Every question answered "Yes" — every element at Level 3.
     */
    public static function allYes(): self
    {
        return self::blank()->answerAll('Yes');
    }

    public function answerAll(?string $answer): self
    {
        foreach (self::elements() as $code => $element) {
            $this->answerElement($code, array_fill(0, $element['question_count'], $answer));
        }

        return $this;
    }

    /**
     * @param  list<?string>  $answers  in question order; null leaves the cell blank
     */
    public function answerElement(string $code, array $answers): self
    {
        $element = self::elements()[$code];

        foreach (range(0, $element['question_count'] - 1) as $offset) {
            $this->cells[self::QuestionnaireSheet]['H'.($element['first_row'] + $offset)] = $answers[$offset] ?? null;
        }

        return $this;
    }

    /**
     * Give an element exactly $yesCount "Yes" answers followed by "No"s.
     */
    public function withYesCount(string $code, int $yesCount): self
    {
        $questionCount = self::elements()[$code]['question_count'];

        return $this->answerElement($code, [
            ...array_fill(0, $yesCount, 'Yes'),
            ...array_fill(0, $questionCount - $yesCount, 'No'),
        ]);
    }

    public function setCell(string $sheet, string $cell, ?string $value): self
    {
        $this->cells[$sheet][$cell] = $value;

        return $this;
    }

    public function renameSheet(string $from, string $to): self
    {
        $this->renamedSheets[$from] = $to;

        return $this;
    }

    public function addSheet(string $title): self
    {
        $this->addedSheets[] = $title;

        return $this;
    }

    public function toUploadedFile(string $name = 'NCSB-SelfAssessment-v1.4.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ncsb');
        copy(base_path('docs/reference/NCSB-SelfAssessment-v1.4.xlsx'), $path);

        $zip = new ZipArchive;
        $zip->open($path);
        $this->writeCells($zip);
        $this->writeSheetChanges($zip);
        $zip->close();

        return new UploadedFile($path, $name, self::MimeType, null, true);
    }

    /**
     * The workbook as PhpSpreadsheet sees it, formulas included — for comparing against the toolkit's own scoring.
     */
    public function toSpreadsheet(): Spreadsheet
    {
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);

        return $reader->load($this->toUploadedFile()->getRealPath());
    }

    /**
     * @return array<string, array{first_row: int, last_row: int, question_count: int}>
     */
    public static function elements(): array
    {
        if (self::$elements === null) {
            $data = json_decode(file_get_contents(base_path('database/seeders/data/ncsb-v1.4.json')), true, flags: JSON_THROW_ON_ERROR);
            self::$elements = collect($data['elements'])->keyBy('code')->map(fn (array $element): array => [
                'first_row' => $element['first_row'],
                'last_row' => $element['last_row'],
                'question_count' => $element['question_count'],
            ])->all();
        }

        return self::$elements;
    }

    private function writeCells(ZipArchive $zip): void
    {
        foreach ($this->cells as $sheet => $cells) {
            $entry = $this->sheetEntry($sheet);
            $xml = $zip->getFromName($entry);

            foreach ($cells as $reference => $value) {
                $pattern = '/<c r="'.$reference.'"([^>]*?)(\/>|>.*?<\/c>)/s';

                if (preg_match($pattern, $xml, $match) !== 1) {
                    throw new RuntimeException("Cell {$reference} not found in sheet '{$sheet}'.");
                }

                $style = preg_match('/\ss="(\d+)"/', $match[1], $styleMatch) === 1 ? " s=\"{$styleMatch[1]}\"" : '';
                $replacement = $value === null
                    ? "<c r=\"{$reference}\"{$style}/>"
                    : "<c r=\"{$reference}\"{$style} t=\"inlineStr\"><is><t xml:space=\"preserve\">".htmlspecialchars($value, ENT_XML1).'</t></is></c>';

                $xml = preg_replace($pattern, $replacement, $xml, 1);
            }

            $zip->addFromString($entry, $xml);
        }
    }

    private function writeSheetChanges(ZipArchive $zip): void
    {
        if ($this->renamedSheets === [] && $this->addedSheets === []) {
            return;
        }

        $workbook = $zip->getFromName('xl/workbook.xml');
        $relationships = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $contentTypes = $zip->getFromName('[Content_Types].xml');

        foreach ($this->renamedSheets as $from => $to) {
            $workbook = str_replace('name="'.htmlspecialchars($from, ENT_XML1).'"', 'name="'.htmlspecialchars($to, ENT_XML1).'"', $workbook);
        }

        foreach ($this->addedSheets as $index => $title) {
            $number = 100 + $index;
            $workbook = str_replace('</sheets>', '<sheet name="'.htmlspecialchars($title, ENT_XML1)."\" sheetId=\"{$number}\" r:id=\"rIdTest{$number}\"/></sheets>", $workbook);
            $relationships = str_replace('</Relationships>', "<Relationship Id=\"rIdTest{$number}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$number}.xml\"/></Relationships>", $relationships);
            $contentTypes = str_replace('</Types>', "<Override PartName=\"/xl/worksheets/sheet{$number}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/></Types>", $contentTypes);
            $zip->addFromString("xl/worksheets/sheet{$number}.xml", '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData/></worksheet>');
        }

        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $relationships);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
    }

    /**
     * The template's six sheets are stored as sheet1.xml … sheet6.xml in workbook order.
     */
    private function sheetEntry(string $sheet): string
    {
        $index = array_search($sheet, config('ncsb.sheets'), true);

        if ($index === false) {
            throw new RuntimeException("Unknown template sheet '{$sheet}'.");
        }

        return 'xl/worksheets/sheet'.($index + 1).'.xml';
    }
}
