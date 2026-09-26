<?php

namespace App\Models;

use App\Enums\Domain;
use App\Enums\MaturityLevel;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

/**
 * NCSB v1.4 reference data — seeded from the official toolkit, never user-editable (PRD §6).
 */
#[Guarded([])]
class NcsbElement extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'domain' => Domain::class,
            'questions' => 'array',
        ];
    }

    /**
     * Requirement text for reaching the given level (used verbatim in remediation actions).
     */
    public function requirementFor(MaturityLevel $level): ?string
    {
        return match ($level) {
            MaturityLevel::Initial => null,
            MaturityLevel::Basic => $this->requirement_l1,
            MaturityLevel::Intermediate => $this->requirement_l2,
            MaturityLevel::Advanced => $this->requirement_l3,
        };
    }
}
