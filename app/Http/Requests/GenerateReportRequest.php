<?php

namespace App\Http\Requests;

use App\Models\Assessment;
use App\Models\ExecutiveReport;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FR-34 / DECISIONS D-56: a cycle that already has a report only gets a new version on explicit confirmation.
 */
class GenerateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ExecutiveReport::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->assessment()->reports()->exists()
            ? ['confirm_new_version' => ['accepted']]
            : [];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_new_version.accepted' => "{$this->assessment()->cycle_label} already has a report. Tick the box to confirm a new version; earlier versions stay available.",
        ];
    }

    private function assessment(): Assessment
    {
        return $this->route('assessment');
    }
}
