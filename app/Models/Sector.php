<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class Sector extends Model
{
    use HasFactory;

    /** @return HasMany<Assessment, $this> */
    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    /** @return HasMany<SectorCoreConstruct, $this> */
    public function coreConstructs(): HasMany
    {
        return $this->hasMany(SectorCoreConstruct::class);
    }

    /** @return list<string> */
    public function disabledCoreCodes(): array
    {
        return $this->coreConstructs()
            ->where('status', SectorCoreConstruct::Disabled)
            ->join('ncsb_elements', 'ncsb_elements.id', '=', 'sector_core_constructs.ncsb_element_id')
            ->orderBy('ncsb_elements.code')
            ->pluck('ncsb_elements.code')
            ->all();
    }

    /** @return HasMany<SectorConstruct, $this> */
    public function constructs(): HasMany
    {
        return $this->hasMany(SectorConstruct::class);
    }
}
