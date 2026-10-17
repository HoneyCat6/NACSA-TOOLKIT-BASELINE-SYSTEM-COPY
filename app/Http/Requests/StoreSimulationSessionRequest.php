<?php

namespace App\Http\Requests;

/**
 * FR-64: a scenario is saved under a name, and only once at least one slider has moved.
 */
class StoreSimulationSessionRequest extends ProjectSimulationRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'overrides' => ['required', $this->keyedByElementCode()],
            'scenario_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'overrides.required' => 'Move at least one slider away from the baseline before saving a scenario.',
            'scenario_name.required' => 'Give the scenario a name.',
        ];
    }
}
