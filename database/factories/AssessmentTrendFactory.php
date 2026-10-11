<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\AssessmentTrend;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssessmentTrend>
 */
class AssessmentTrendFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assessment_id' => Assessment::factory(),
            'prev_assessment_id' => Assessment::factory(),
            'overall_delta' => 0,
        ];
    }
}
