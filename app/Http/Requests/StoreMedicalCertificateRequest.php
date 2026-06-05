<?php

namespace App\Http\Requests;

use App\Models\Absence;
use App\Support\DocumentFileValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicalCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coverage_type' => ['required', 'string', Rule::in([Absence::COVERAGE_FULL_DAY, Absence::COVERAGE_HOURS])],
            'start_date' => ['required_if:coverage_type,'.Absence::COVERAGE_FULL_DAY, 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'date' => ['required_if:coverage_type,'.Absence::COVERAGE_HOURS, 'date'],
            'start_time' => ['required_if:coverage_type,'.Absence::COVERAGE_HOURS, 'date_format:H:i'],
            'end_time' => ['required_if:coverage_type,'.Absence::COVERAGE_HOURS, 'date_format:H:i', 'after:start_time'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'files' => ['nullable', 'array'],
            'files.*' => [
                'required',
                'file',
                function ($attribute, $value, $fail) {
                    if ($value && $value->getSize() > 5 * 1024 * 1024) {
                        $fail(sprintf('Arquivo muito grande (máx. 5MB): %s', $value->getClientOriginalName()));
                    }

                    if ($value) {
                        $validationError = DocumentFileValidator::validate($value);

                        if ($validationError !== null) {
                            $fail($validationError);
                        }
                    }
                },
            ],
        ];
    }
}
