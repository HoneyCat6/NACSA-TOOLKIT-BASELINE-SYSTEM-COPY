<?php

namespace Database\Factories;

use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sector> */
class SectorFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'code_of_practice' => 'Sector Code of Practice'];
    }
}
