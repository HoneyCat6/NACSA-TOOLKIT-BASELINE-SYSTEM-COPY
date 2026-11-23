<?php

namespace App\Http\Requests\Admin;

use App\Enums\Domain;
use App\Models\Sector;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectorConstructRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', Sector::class);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/'],
            'name' => ['required', 'string', 'max:150'],
            'domain' => ['required', Rule::enum(Domain::class)],
            'requirement' => ['required', 'string', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Start the code with a letter or number. Use letters, numbers, periods, underscores or hyphens.'];
    }
}
