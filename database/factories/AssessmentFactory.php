<?php

namespace Database\Factories;

use App\Enums\Band;
use App\Enums\Domain;
use App\Models\Assessment;
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
}
