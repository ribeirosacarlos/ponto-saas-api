<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OvertimeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by controller policies/middleware.
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date', 'before_or_equal:to'],
            'to' => ['nullable', 'date'],
            'include_days' => ['nullable', 'in:0,1'],
        ];
    }
}
