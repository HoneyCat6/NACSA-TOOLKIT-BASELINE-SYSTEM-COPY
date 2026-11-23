<?php

namespace App\Models;

use App\Enums\MaturityLevel;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class SectorConstructScore extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['maturity_level' => MaturityLevel::class];
    }

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<SectorConstruct, $this> */
    public function sectorConstruct(): BelongsTo
    {
        return $this->belongsTo(SectorConstruct::class);
    }
}
