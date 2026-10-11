<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DECISIONS D-35: undoing an upload requires a reason, recorded in the audit log.
 */
class DeleteAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('delete', $this->route('assessment'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Give a reason for deleting this cycle. It is kept in the audit log.',
            'reason.min' => 'Give a reason of at least 5 characters.',
        ];
    }
}
