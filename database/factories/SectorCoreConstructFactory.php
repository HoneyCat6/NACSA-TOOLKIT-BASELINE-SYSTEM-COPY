<?php

namespace Database\Factories;

use App\Models\NcsbElement;
use App\Models\Sector;
use App\Models\SectorCoreConstruct;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SectorCoreConstruct> */
class SectorCoreConstructFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sector_id' => Sector::factory(),
            'ncsb_element_id' => fn (): int => NcsbElement::query()->firstOrFail()->id,
            'status' => SectorCoreConstruct::Disabled,
            'reason' => fake()->sentence(),
        ];
    }
}
