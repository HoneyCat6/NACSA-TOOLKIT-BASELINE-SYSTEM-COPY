<?php

namespace Database\Factories;

use App\Models\ComplianceDeadline;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceDeadline>
 */
class ComplianceDeadlineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'officer_id' => User::factory()->complianceOfficer(),
            'secondary_contact_id' => User::factory()->executiveReviewer(),
            'title' => 'NCSB self-assessment '.fake()->numerify('20## H#'),
            'description' => null,
            'due_date' => today()->addDays(60),
            'lead_days' => ComplianceDeadline::DefaultLeadDays,
        ];
    }

    /**
     * Due this many days from today (negative = overdue).
     */
    public function dueIn(int $days): static
    {
        return $this->state(fn (array $attributes): array => ['due_date' => today()->addDays($days)]);
    }

    public function acknowledged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'acknowledged_at' => now(),
            'acknowledged_by' => fn (array $attributes) => $attributes['officer_id'],
        ]);
    }

    public function met(): static
    {
        return $this->state(fn (array $attributes): array => ['met_at' => now()]);
    }
}
