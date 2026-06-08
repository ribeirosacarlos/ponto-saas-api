<?php

namespace App\Http\Requests;

use App\Models\Absence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'type' => ['nullable', 'string', 'max:120'],
            'coverage_type' => ['required', 'string', Rule::in([Absence::COVERAGE_FULL_DAY, Absence::COVERAGE_HOURS])],
            'start_date' => ['required_if:coverage_type,'.Absence::COVERAGE_FULL_DAY, 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'date' => ['required_if:coverage_type,'.Absence::COVERAGE_HOURS, 'date'],
            'start_time' => ['required_if:coverage_type,'.Absence::COVERAGE_HOURS, 'date_format:H:i'],
            'end_time' => ['required_if:coverage_type,'.Absence::COVERAGE_HOURS, 'date_format:H:i', 'after:start_time'],
            'comment' => ['nullable', 'string'],
            'status' => ['nullable', 'string', Rule::in([
                Absence::STATUS_RECORDED,
                Absence::STATUS_APPROVED,
                Absence::STATUS_PENDING,
                Absence::STATUS_REJECTED,
                Absence::STATUS_CANCELED,
            ])],
            'counts_for_accrual' => ['nullable', 'boolean'],
        ];
    }
}
