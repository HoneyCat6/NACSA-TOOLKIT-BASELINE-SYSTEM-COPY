<?php

namespace App\Http\Requests\Admin;

use App\Models\Sector;
use Illuminate\Foundation\Http\FormRequest;

class StoreSectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', Sector::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', 'unique:sectors,name'],
            'code_of_practice' => ['nullable', 'string', 'max:255'],
        ];
    }
}
