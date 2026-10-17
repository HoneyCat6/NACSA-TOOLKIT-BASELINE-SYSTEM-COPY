<?php

namespace App\Http\Requests;

use App\Models\Assessment;
use App\Models\NcsbElement;
use App\Models\SimulationSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /simulator/project` (TECH_SPEC §11.1): a baseline cycle plus hypothetical levels keyed by element code.
 */
class ProjectSimulationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SimulationSession::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'baseline_assessment_id' => ['required', 'integer', Rule::exists('assessments', 'id')],
            'overrides' => ['present', $this->keyedByElementCode()],
            'overrides.*' => ['required', 'integer', 'between:0,3'],
        ];
    }

    /**
     * Only the 33 seeded element codes are accepted as keys.
     */
    protected function keyedByElementCode(): string
    {
        return 'array:'.NcsbElement::query()->orderBy('sort_order')->pluck('code')->implode(',');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'overrides.array' => 'Hypothetical levels must be keyed by NCSB element code.',
            'overrides.*.between' => 'Each hypothetical level must be between 0 and 3.',
        ];
    }

    public function baseline(): Assessment
    {
        return Assessment::query()->findOrFail($this->integer('baseline_assessment_id'));
    }

    /**
     * @return array<string, int>
     */
    public function overrides(): array
    {
        return array_map(intval(...), $this->validated('overrides', []));
    }
}
