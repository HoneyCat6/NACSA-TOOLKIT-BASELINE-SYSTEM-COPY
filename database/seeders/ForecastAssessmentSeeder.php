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
 * Nine synthetic assessments, four months apart, for forecasting demonstrations.
 *
 * Uses real answer interpretation, scoring and trends without creating workbook files or sending email.
 * Requires an empty assessment history so imported cycles and their trends are never changed.
 */
class ForecastAssessmentSeeder extends Seeder
{
    /**
     * Total maturity points distributed across each domain's elements in chronological order.
     * Governance and identification improve; protection declines late; recovery levels off.
     *
     * @var array<string, list<int>>
     */
    private const DomainPoints = [
        'GOVERN' => [4, 6, 8, 10, 12, 13, 14, 15, 16],
        'IDENTIFY' => [1, 2, 2, 3, 3, 4, 4, 5, 5],
        'PROTECT' => [10, 14, 18, 22, 26, 30, 33, 32, 30],
        'DETECT' => [0, 1, 1, 2, 2, 3, 4, 3, 3],
        'RESPOND' => [0, 1, 1, 1, 2, 2, 2, 1, 1],
        'RECOVER' => [0, 1, 2, 2, 3, 3, 4, 4, 4],
    ];

    public function run(ExcelParserService $parser, ScoringService $scoring, TrendService $trends): void
    {
        $hashes = array_map(fn (int $cycle): string => hash('sha256', 'forecast-demo:'.$cycle), range(0, 8));
        $existingHashes = Assessment::query()->chronological()->pluck('file_hash')->all();

        if ($existingHashes === $hashes) {
            return;
        }

        if ($existingHashes !== []) {
            throw new RuntimeException('Forecast demo requires an empty assessment history; existing assessments were not changed.');
        }

        $uploader = User::query()->where('role', Role::ComplianceOfficer)->where('is_active', true)->orderBy('id')->first()
            ?? throw new RuntimeException('No active Compliance Officer. Run `php artisan db:seed` first.');
        $elements = NcsbElement::query()->orderBy('sort_order')->get();

        if ($elements->count() !== 33) {
            throw new RuntimeException('Seed the 33 NCSB reference elements with `php artisan db:seed` first.');
        }

        DB::transaction(function () use ($parser, $scoring, $trends, $uploader, $elements, $hashes): void {
            foreach (range(0, 8) as $cycle) {
                $date = Carbon::create(2024, 1, 15)->addMonths($cycle * 4);
                $label = $date->year.'_'.(($cycle % 3) + 1).'_Assessment';
                $scored = $scoring->score($parser->interpretAnswers($elements, $this->answersFor($cycle, $elements)));
                $assessment = $scoring->persist($scored, [
                    'created_by' => $uploader->id,
                    'assessment_date' => $date,
                    'cycle_label' => $label,
                    'original_filename' => 'NCSB-SelfAssessment-v1.4 ('.$label.').xlsx',
                    'stored_path' => 'assessments/'.str($label)->slug().'.xlsx',
                    'file_hash' => $hashes[$cycle],
                ]);
                $uploadedAt = $date->copy()->addDays(2)->setTime(10, 30);
                $assessment->forceFill(['created_at' => $uploadedAt, 'updated_at' => $uploadedAt])->saveQuietly();
                $trend = $trends->computeTrend($assessment);

                if ($trend !== null) {
                    $trends->detectDegradation($trend)
                        ->each(fn (DegradationAlert $alert) => $alert->update(['triggered_at' => $uploadedAt]));
                }
            }
        });
    }

    /**
     * @param  Collection<int, NcsbElement>  $elements
     * @return array<string, list<?string>>
     */
    private function answersFor(int $cycle, Collection $elements): array
    {
        $answers = [];

        foreach (Domain::cases() as $domain) {
            $domainElements = $elements->where('domain', $domain)->values();
            $points = self::DomainPoints[$domain->value][$cycle];
            $baseLevel = intdiv($points, $domainElements->count());
            $extraPoints = $points % $domainElements->count();

            foreach ($domainElements as $index => $element) {
                $level = $baseLevel + ($index < $extraPoints ? 1 : 0);
                $yesCount = $level === 0 ? 0 : $element->{'threshold_l'.$level};
                $answers[$element->code] = array_slice([
                    ...array_fill(0, $yesCount, 'Yes'),
                    'No',
                    ...array_fill(0, $element->question_count, null),
                ], 0, $element->question_count);
            }
        }

        return $answers;
    }
}
