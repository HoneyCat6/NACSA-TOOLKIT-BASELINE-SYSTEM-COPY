<?php

namespace Database\Seeders;

use App\Enums\Domain;
use App\Enums\Role;
use App\Models\Assessment;
use App\Models\DegradationAlert;
use App\Models\NcsbElement;
use App\Models\User;
use App\Services\ExcelParserService;
use App\Services\ScoringService;
use App\Services\TrendService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Four demo assessment cycles for local review of the results and trend screens.
 *
 * Answers go through the real answer rules, scoring and trend engine; only the .xlsx file itself is skipped.
 * Storyline: steady improvement, a small IDENTIFY dip in 2025 H2 (acknowledged), and PROTECT and RESPOND
 * declines in 2026 H1 (still active).
 */
class DemoAssessmentSeeder extends Seeder
{
    /**
     * @var list<array{string, string}> cycle label, assessment date
     */
    private const Cycles = [
        ['Cycle 2024 H2', '2024-12-16'],
        ['Cycle 2025 H1', '2025-06-16'],
        ['Cycle 2025 H2', '2025-12-15'],
        ['Cycle 2026 H1', '2026-06-15'],
    ];

    /**
     * Level each element reaches in Cycle 2025 H2, repeated across the 33 elements.
     *
     * @var list<int>
     */
    private const BaseLevels = [3, 2, 2, 1, 3, 2, 0, 2, 3, 1, 2];

    public function run(ExcelParserService $parser, ScoringService $scoring, TrendService $trends): void
    {
        $uploader = User::query()->where('role', Role::ComplianceOfficer)->where('is_active', true)->orderBy('id')->first()
            ?? throw new RuntimeException('No active Compliance Officer. Run `php artisan db:seed` first.');

        $elements = NcsbElement::query()->orderBy('sort_order')->get();

        foreach (self::Cycles as $cycle => [$label, $date]) {
            if (Assessment::query()->where('cycle_label', $label)->exists()) {
                continue;
            }

            $uploadedAt = Carbon::parse($date)->addDays(2)->setTime(10, 30);

            DB::transaction(function () use ($parser, $scoring, $trends, $elements, $cycle, $label, $date, $uploader, $uploadedAt): void {
                $scored = $scoring->score($parser->interpretAnswers($elements, $this->answersFor($cycle, $elements)));

                $assessment = $scoring->persist($scored, [
                    'created_by' => $uploader->id,
                    'assessment_date' => $date,
                    'cycle_label' => $label,
                    'original_filename' => 'NCSB-SelfAssessment-v1.4 ('.$label.').xlsx',
                    'stored_path' => 'assessments/demo-'.str($label)->slug().'.xlsx',
                    'file_hash' => hash('sha256', 'demo:'.$label),
                ]);
                $assessment->forceFill(['created_at' => $uploadedAt, 'updated_at' => $uploadedAt])->saveQuietly();

                $trend = $trends->computeTrend($assessment);

                if ($trend !== null) {
                    $trends->detectDegradation($trend)
                        ->each(fn (DegradationAlert $alert) => $alert->update(['triggered_at' => $uploadedAt]));
                }
            });
        }

        $this->acknowledgeEarlierAlerts($uploader);
    }

    /**
     * Raw answers as they would be typed into the toolkit: "Yes"s, a "No", then blanks (the toolkit's skips).
     *
     * @param  Collection<int, NcsbElement>  $elements
     * @return array<string, list<?string>>
     */
    private function answersFor(int $cycle, Collection $elements): array
    {
        $answers = [];

        foreach ($elements as $index => $element) {
            $level = $this->levelFor($cycle, $element, $index);

            if ($level === null) {
                $answers[$element->code] = array_fill(0, $element->question_count, null);

                continue;
            }

            $yesCount = $level === 0 ? 0 : $element->{'threshold_l'.$level};
            $answers[$element->code] = array_slice([
                ...array_fill(0, $yesCount, 'Yes'),
                'No',
                ...array_fill(0, $element->question_count, null),
            ], 0, $element->question_count);
        }

        if ($cycle === 1) {
            // Show defaulted cells and parse warnings on one results screen.
            $answers['GV-03'] = $this->replaceFirstNo($answers['GV-03'], null);
            $answers['PR-07'] = $this->replaceFirstNo($answers['PR-07'], null);
            $answers['PR-09'] = $this->replaceFirstNo($answers['PR-09'], 'N/A');
            $answers['DE-02'] = ['Yes', 'No', 'Yes'];
        }

        return $answers;
    }

    private function levelFor(int $cycle, NcsbElement $element, int $index): ?int
    {
        $base = self::BaseLevels[$index % count(self::BaseLevels)];

        return match ($cycle) {
            0 => $element->code === 'RC-02' ? null : max(0, $base - 2),
            1 => max(0, $base - 1),
            2 => $element->domain === Domain::Identify ? max(0, $base - 2) : $base,
            default => match (true) {
                $element->code === 'RS-01' => max(0, $base - 2),
                $element->domain === Domain::Protect => $index % 3 === 0 ? max(0, $base - 1) : $base,
                $element->domain === Domain::Identify => $base,
                default => min(3, $base + 1),
            },
        };
    }

    /**
     * @param  list<?string>  $answers
     * @return list<?string>
     */
    private function replaceFirstNo(array $answers, ?string $replacement): array
    {
        $position = array_search('No', $answers, true);

        if ($position !== false) {
            $answers[$position] = $replacement;
        }

        return $answers;
    }

    /**
     * Alerts from every cycle except the latest have been reviewed; the latest cycle's alerts stay active.
     */
    private function acknowledgeEarlierAlerts(User $officer): void
    {
        $latestTrendId = Assessment::query()->orderByDesc('assessment_date')->first()?->trend?->id;

        DegradationAlert::query()
            ->active()
            ->where('assessment_trend_id', '!=', $latestTrendId)
            ->get()
            ->each(fn (DegradationAlert $alert) => $alert->update([
                'acknowledged' => true,
                'acknowledged_by' => $officer->id,
                'acknowledged_at' => $alert->triggered_at->copy()->addDay(),
            ]));
    }
}
