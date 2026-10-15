<?php

namespace Database\Factories;

use App\Enums\Band;
use App\Enums\Domain;
use App\Enums\ElementStatus;
use App\Enums\MaturityLevel;
use App\Models\Assessment;
use App\Models\NcsbElement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Assessment header rows without element rows — for history, trend and dashboard tests.
 *
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domainScores = [];
        foreach (Domain::cases() as $domain) {
            $domainScores[$domain->scoreColumn()] = 2.0;
        }

        return [
            'created_by' => User::factory()->complianceOfficer(),
            'assessment_date' => fake()->unique()->dateTimeBetween('-5 years', '-1 day'),
            'cycle_label' => 'Cycle '.fake()->unique()->numerify('#####'),
            'original_filename' => 'NCSB-SelfAssessment-v1.4.xlsx',
            'stored_path' => 'assessments/'.fake()->uuid().'.xlsx',
            'file_hash' => fake()->unique()->sha256(),
            'overall_score' => 0.606,
            'overall_band' => Band::Intermediate,
            ...$domainScores,
        ];
    }

    /**
     * @param  array<string, ?float>  $scores  domain value => 0–3 score
     */
    public function withDomainScores(array $scores): static
    {
        return $this->state(fn (array $attributes): array => collect($scores)
            ->mapWithKeys(fn (?float $score, string $domain): array => [Domain::from($domain)->scoreColumn() => $score])
            ->all());
    }

    /**
     * All 33 element rows at the given levels, with domain scores, overall score and band to match (PRD §7.3).
     *
     * Needs the NcsbElementSeeder. Each element gets exactly its level's threshold of "Yes" answers.
     *
     * @param  array<string, ?int>  $levels  element code => level 0–3, or null for Unanswered; others get $defaultLevel
     */
    public function withElements(array $levels = [], int $defaultLevel = 2): static
    {
        $levelOf = fn (NcsbElement $element): ?int => array_key_exists($element->code, $levels) ? $levels[$element->code] : $defaultLevel;

        return $this
            ->state(function () use ($levelOf): array {
                $references = NcsbElement::query()->orderBy('sort_order')->get();
                $scored = $references->reject(fn (NcsbElement $element): bool => $levelOf($element) === null);
                $overallScore = round($scored->filter(fn (NcsbElement $element): bool => $levelOf($element) >= 1)->count() / $references->count(), 3);

                $domainScores = [];
                foreach (Domain::cases() as $domain) {
                    $domainLevels = $scored->where('domain', $domain)->map($levelOf);
                    $domainScores[$domain->scoreColumn()] = $domainLevels->isEmpty() ? null : round($domainLevels->avg(), 2);
                }

                return [
                    ...$domainScores,
                    'overall_score' => $overallScore,
                    'overall_band' => Band::fromScore($overallScore),
                    'unanswered_count' => $references->count() - $scored->count(),
                ];
            })
            ->afterCreating(function (Assessment $assessment) use ($levelOf): void {
                $assessment->elements()->createMany(NcsbElement::query()->orderBy('sort_order')->get()->map(function (NcsbElement $element) use ($levelOf): array {
                    $level = $levelOf($element);
                    $yesCount = $level === null || $level === 0 ? 0 : $element->{'threshold_l'.$level};

                    return [
                        'ncsb_element_id' => $element->id,
                        'domain' => $element->domain,
                        'element_code' => $element->code,
                        'yes_count' => $yesCount,
                        'maturity_level' => $level === null ? null : MaturityLevel::from($level),
                        'status' => $level === null ? ElementStatus::Unanswered : ElementStatus::Scored,
                        'responses' => $level === null
                            ? array_fill(0, $element->question_count, null)
                            : array_map(fn (int $index): bool => $index < $yesCount, range(0, $element->question_count - 1)),
                    ];
                })->all());
            });
    }
}
