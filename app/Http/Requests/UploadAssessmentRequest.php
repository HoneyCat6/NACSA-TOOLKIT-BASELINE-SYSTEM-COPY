<?php

namespace App\Http\Requests;

use App\Models\Assessment;
use App\Services\AssessmentImportService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * FR-10, FR-16, SEC-07, DECISIONS D-32, D-33.
 */
class UploadAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Assessment::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:xlsx', 'mimes:xlsx', 'max:'.config('ncsb.max_upload_kb')],
            'cycle_label' => ['required', 'string', 'max:50', Rule::unique('assessments', 'cycle_label')],
            'assessment_date' => ['required', 'date', 'before_or_equal:today'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'sector_levels' => ['sometimes', 'array'],
            'sector_levels.*' => ['nullable', 'integer', 'between:0,3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Only the official NCSB v1.4 toolkit (.xlsx) can be uploaded.',
            'file.mimes' => 'The file is not a valid .xlsx workbook.',
            'file.max' => 'The file must not be larger than '.(config('ncsb.max_upload_kb') / 1024).' MB.',
            'cycle_label.unique' => 'This cycle label is already used by another assessment.',
            'assessment_date.before_or_equal' => 'The assessment date cannot be in the future.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['assessment_date', 'sector_id'])) {
                    return;
                }

                $sectorId = $this->filled('sector_id') ? $this->integer('sector_id') : null;
                $latest = Assessment::query()->where('sector_id', $sectorId)->newestFirst()->first();

                if ($latest !== null && $this->date('assessment_date')->lte($latest->assessment_date)) {
                    $validator->errors()->add('assessment_date', "The assessment date must be later than the latest cycle, {$latest->cycle_label} ({$latest->assessment_date->format('j M Y')}).");
                }
            },
            function (Validator $validator): void {
                if ($validator->errors()->has('file')) {
                    return;
                }

                $duplicate = Assessment::query()->where('file_hash', hash_file('sha256', $this->file('file')->getRealPath()))->first();

                if ($duplicate !== null) {
                    $validator->errors()->add('file', "This exact file was already uploaded as {$duplicate->cycle_label} on {$duplicate->created_at->format('j M Y')}.");
                }
            },
        ];
    }

    /**
     * D-31: every rejected file is audited.
     */
    protected function failedValidation(Validator $validator): void
    {
        $file = $this->file('file');

        if ($file instanceof UploadedFile && $validator->errors()->has('file')) {
            $this->container->make(AssessmentImportService::class)
                ->recordRejection($file, $validator->errors()->first('file'), $this->user());
        }

        parent::failedValidation($validator);
    }
}
