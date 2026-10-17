<?php

namespace Database\Factories;

use App\Enums\NotificationType;
use App\Models\ComplianceDeadline;
use App\Models\DeadlineNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeadlineNotification>
 */
class DeadlineNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'compliance_deadline_id' => ComplianceDeadline::factory(),
            'type' => NotificationType::Reminder,
            'lead_day' => 30,
            'due_date' => fn (array $attributes) => ComplianceDeadline::query()->find($attributes['compliance_deadline_id'])->due_date,
            'recipient_id' => fn (array $attributes) => ComplianceDeadline::query()->find($attributes['compliance_deadline_id'])->officer_id,
            'dispatched_at' => now(),
            'delivered' => true,
        ];
    }

    public function escalation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => NotificationType::Escalation,
            'lead_day' => 0,
            'recipient_id' => fn (array $attributes) => ComplianceDeadline::query()->find($attributes['compliance_deadline_id'])->secondary_contact_id,
        ]);
    }

    public function failed(string $reason = 'Connection could not be established with host smtp.example.com'): static
    {
        return $this->state(fn (array $attributes): array => ['delivered' => false, 'failure_reason' => $reason]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => ['delivered' => false, 'failure_reason' => null]);
    }
}
