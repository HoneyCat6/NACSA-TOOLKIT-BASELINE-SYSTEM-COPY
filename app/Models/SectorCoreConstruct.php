<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class SectorCoreConstruct extends Model
{
    use HasFactory;

    public const Active = 'active';

    public const Disabled = 'disabled';

    /** @return BelongsTo<Sector, $this> */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /** @return BelongsTo<NcsbElement, $this> */
    public function ncsbElement(): BelongsTo
    {
        return $this->belongsTo(NcsbElement::class);
    }
}
