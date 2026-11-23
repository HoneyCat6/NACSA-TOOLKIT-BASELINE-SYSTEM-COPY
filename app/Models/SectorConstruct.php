<?php

namespace App\Models;

use App\Enums\Domain;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Guarded(['id'])]
class SectorConstruct extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['domain' => Domain::class];
    }

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /** @return HasMany<SectorConstructScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(SectorConstructScore::class);
    }
}
