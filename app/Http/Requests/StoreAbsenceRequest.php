<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'type' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'comment' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:50'],
            'counts_for_accrual' => ['nullable', 'boolean'],
        ];
    }
}
