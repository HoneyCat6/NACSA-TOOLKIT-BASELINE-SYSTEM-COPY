<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Models\NcsbElement;
use App\Models\Sector;
use App\Models\SectorCoreConstruct;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SectorConstructService
{
    public function __construct(private AuditService $audit) {}

    /** @param array{name: string, code_of_practice?: ?string} $attributes */
    public function createSector(array $attributes, User $actor): Sector
    {
        return DB::transaction(function () use ($attributes, $actor): Sector {
            $sector = Sector::query()->createOrFirst(
                ['name' => $attributes['name']],
                ['code_of_practice' => $attributes['code_of_practice'] ?? null],
            );

            if (! $sector->wasRecentlyCreated) {
                throw ValidationException::withMessages(['name' => 'A sector with this name already exists.']);
            }

            $this->audit->log('sector.created', AuditCategory::Technical, $sector, $attributes, $actor);

            return $sector;
        });
    }

    public function setCoreStatus(Sector $sector, NcsbElement $element, string $status, ?string $reason, User $actor): bool
    {
        return DB::transaction(function () use ($sector, $element, $status, $reason, $actor): bool {
            $sector = Sector::query()->lockForUpdate()->findOrFail($sector->id);
            $override = $sector->coreConstructs()->firstOrNew(['ncsb_element_id' => $element->id]);
            $previous = $override->status ?? SectorCoreConstruct::Active;

            if ($previous === $status) {
                return false;
            }

            $override->fill(['status' => $status, 'reason' => $reason])->save();

            $this->audit->log(
                $status === SectorCoreConstruct::Disabled ? 'construct.dropped' : 'construct.restored',
                AuditCategory::Technical,
                $override,
                [
                    'sector_id' => $sector->id,
                    'sector' => $sector->name,
                    'construct' => $element->code,
                    'from' => $previous,
                    'to' => $status,
                    'reason' => $reason,
                ],
                $actor,
            );

            return true;
        });
    }

    /** @param array{code: string, name: string, domain: string, requirement: string} $attributes */
    public function addConstruct(Sector $sector, array $attributes, User $actor): void
    {
        DB::transaction(function () use ($sector, $attributes, $actor): void {
            $sector = Sector::query()->lockForUpdate()->findOrFail($sector->id);

            if (NcsbElement::query()->where('code', strtoupper($attributes['code']))->exists()) {
                throw ValidationException::withMessages(['code' => 'This code is reserved for a core NCSB construct.']);
            }

            if ($sector->constructs()->where('code', $attributes['code'])->exists()) {
                throw ValidationException::withMessages(['code' => 'This construct code is already used in this sector.']);
            }

            $construct = $sector->constructs()->create($attributes);

            $this->audit->log('construct.added', AuditCategory::Technical, $construct, [
                'sector_id' => $sector->id,
                'sector' => $sector->name,
                ...$attributes,
            ], $actor);
        });
    }
}
