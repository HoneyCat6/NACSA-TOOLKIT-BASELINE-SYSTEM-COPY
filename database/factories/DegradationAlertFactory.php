<?php

namespace Database\Factories;

use App\Enums\Domain;
use App\Models\AssessmentTrend;
use App\Models\DegradationAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DegradationAlert>
 */
class DegradationAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_trend_id' => AssessmentTrend::factory(),
            'domain' => Domain::Protect,
            'score_drop' => 0.5,
            'acknowledged' => false,
            'triggered_at' => now(),
        ];
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'acknowledged' => true,
            'acknowledged_by' => User::factory()->complianceOfficer(),
            'acknowledged_at' => now(),
        ]);
    }
}
