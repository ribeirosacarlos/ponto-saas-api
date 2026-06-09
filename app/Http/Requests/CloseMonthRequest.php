<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseMonthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'uuid', 'exists:users,id'],
            'reference_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'reference_month' => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }
}
