<?php

namespace App\Http\Requests\Admin;

use App\Models\Sector;
use App\Models\SectorCoreConstruct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSectorCoreConstructRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', Sector::class);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([SectorCoreConstruct::Active, SectorCoreConstruct::Disabled])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
