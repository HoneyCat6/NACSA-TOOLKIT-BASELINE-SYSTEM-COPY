<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * FR-40 edit. A changed due date re-arms the reminders and clears the acknowledgement (DECISIONS D-44).
 */
class UpdateDeadlineRequest extends StoreDeadlineRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('deadline'));
    }

    /**
     * An overdue deadline can still be edited; only a new due date has to be today or later.
     *
     * @return array<int, mixed>
     */
    protected function dueDateRules(): array
    {
        $dueDateChanged = $this->input('due_date') !== $this->route('deadline')->due_date->toDateString();

        return ['required', 'date_format:Y-m-d', Rule::when($dueDateChanged, 'after_or_equal:today')];
    }
}
