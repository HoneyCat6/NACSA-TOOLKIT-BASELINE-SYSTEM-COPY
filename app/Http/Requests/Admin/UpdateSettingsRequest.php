<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DECISIONS D-51: organisation name and report footer.
 */
class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::Admin);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'organisation_name' => ['required', 'string', 'max:150'],
            'report_footer' => ['nullable', 'string', 'max:500'],
        ];
    }
}
