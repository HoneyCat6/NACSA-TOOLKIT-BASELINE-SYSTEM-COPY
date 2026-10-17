<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Models\ComplianceDeadline;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FR-40: title, due date, primary and escalation contacts, and lead-time intervals (DECISIONS D-47).
 */
class StoreDeadlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ComplianceDeadline::class);
    }

    /**
     * Lead days arrive as one comma-separated field, e.g. "30, 14, 7".
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('lead_days'))) {
            $this->merge([
                'lead_days' => array_values(array_filter(
                    array_map('trim', explode(',', $this->input('lead_days'))),
                    fn (string $leadDay): bool => $leadDay !== '',
                )),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_date' => $this->dueDateRules(),
            'officer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('role', Role::ComplianceOfficer->value)->where('is_active', true),
            ],
            'secondary_contact_id' => [
                'required',
                'integer',
                'different:officer_id',
                Rule::exists('users', 'id')
                    ->whereIn('role', [Role::ComplianceOfficer->value, Role::ExecutiveReviewer->value])
                    ->where('is_active', true),
            ],
            'lead_days' => ['required', 'array', 'min:1', 'max:5'],
            'lead_days.*' => ['integer', 'distinct', 'between:1,365'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_date.after_or_equal' => 'The due date cannot be in the past.',
            'officer_id.exists' => 'Choose an active Compliance Officer as the primary contact.',
            'secondary_contact_id.required' => 'Choose who is alerted if the deadline is missed.',
            'secondary_contact_id.exists' => 'Choose an active Compliance Officer or Executive Reviewer as the escalation contact.',
            'secondary_contact_id.different' => 'The escalation contact must be someone other than the primary contact.',
            'lead_days.required' => 'Give at least one reminder interval, e.g. 30, 14, 7.',
            'lead_days.max' => 'Give at most 5 reminder intervals.',
            'lead_days.*.integer' => 'Reminder intervals must be whole numbers of days, e.g. 30, 14, 7.',
            'lead_days.*.distinct' => 'Each reminder interval can be listed once.',
            'lead_days.*.between' => 'Reminder intervals must be between 1 and 365 days.',
        ];
    }

    /**
     * The validated lead days as integers, largest first.
     *
     * @return list<int>
     */
    public function leadDays(): array
    {
        $leadDays = array_map('intval', $this->validated('lead_days'));
        rsort($leadDays);

        return $leadDays;
    }

    /**
     * @return array<int, mixed>
     */
    protected function dueDateRules(): array
    {
        return ['required', 'date_format:Y-m-d', 'after_or_equal:today'];
    }
}
