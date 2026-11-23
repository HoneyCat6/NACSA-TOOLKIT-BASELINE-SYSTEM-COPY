<?php

namespace Database\Factories;

use App\Enums\Domain;
use App\Models\Sector;
use App\Models\SectorConstruct;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SectorConstruct> */
class SectorConstructFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sector_id' => Sector::factory(),
            'code' => fake()->unique()->numerify('SC-####'),
            'name' => fake()->words(3, true),
            'domain' => Domain::cases()[0],
            'requirement' => fake()->sentence(),
        ];
    }
}
