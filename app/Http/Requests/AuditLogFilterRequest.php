<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Filters shared by the Admin's technical log and the Compliance Officer's audit trail (DECISIONS D-54).
 * Access is enforced by the route's role middleware and each controller.
 */
class AuditLogFilterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
