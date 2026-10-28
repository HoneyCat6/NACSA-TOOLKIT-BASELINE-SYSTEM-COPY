<?php

namespace Database\Factories;

use App\Enums\Band;
use App\Models\Assessment;
use App\Models\SimulationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A saved scenario on a header-only baseline cycle — for listing, reloading and authorization tests.
 *
 * @extends Factory<SimulationSession>
 */
class SimulationSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory()->complianceOfficer(),
            'baseline_assessment_id' => Assessment::factory(),
            'scenario_name' => 'Scenario '.fake()->unique()->numerify('###'),
            'modified_responses' => ['PR-09' => 3],
            'simulated_score' => 0.636,
            'simulated_band' => Band::Intermediate,
        ];
    }
}
