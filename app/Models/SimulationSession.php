<?php

namespace App\Models;

use App\Enums\Band;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Guarded(['id'])]
class SimulationSession extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'modified_responses' => 'encrypted:array',
            'simulated_score' => 'decimal:3',
            'simulated_band' => Band::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function baseline(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'baseline_assessment_id');
    }
}
