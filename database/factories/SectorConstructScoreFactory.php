<?php

namespace Database\Factories;

use App\Enums\MaturityLevel;
use App\Models\Assessment;
use App\Models\SectorConstruct;
use App\Models\SectorConstructScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SectorConstructScore> */
class SectorConstructScoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sector_construct_id' => SectorConstruct::factory(),
            'assessment_id' => fn (array $attributes): Assessment => Assessment::factory()->create([
                'sector_id' => SectorConstruct::query()->findOrFail($attributes['sector_construct_id'])->sector_id,
            ]),
            'maturity_level' => MaturityLevel::Basic,
        ];
    }
}
