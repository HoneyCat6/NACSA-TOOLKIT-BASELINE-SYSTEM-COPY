<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Default factory password — satisfies the FR-01 complexity rules.
     */
    public const Password = 'password';

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(self::Password),
            'role' => Role::ComplianceOfficer,
            'is_active' => true,
            'password_changed_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn(array $attributes) => ['role' => Role::Admin]);
    }

    public function complianceOfficer(): static
    {
        return $this->state(fn(array $attributes) => ['role' => Role::ComplianceOfficer]);
    }

    public function executiveReviewer(): static
    {
        return $this->state(fn(array $attributes) => ['role' => Role::ExecutiveReviewer]);
    }

    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => ['is_active' => false]);
    }

    public function passwordExpired(): static
    {
        return $this->state(fn(array $attributes) => [
            'password_changed_at' => now()->subDays(config('security.password_expiry_days') + 1),
        ]);
    }
}
