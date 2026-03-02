<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLeavePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:255'],
            'days_per_year'         => ['nullable', 'numeric', 'min:0'],
            'accrual_rate_per_month'=> ['nullable', 'numeric', 'min:0'],
            'annual_entitlement_days'=> ['nullable', 'numeric', 'min:0'],
            'accrual_basis'         => ['nullable', 'in:calendar_days,scheduled_workdays'],
            'day_work_threshold_minutes' => ['nullable', 'integer', 'min:1'],
            'counting_method'       => ['nullable', 'in:calendar_days,working_days'],
            'allow_carry_over'      => ['nullable', 'boolean'],
            'carry_over_limit_days' => ['nullable', 'numeric', 'min:0'],
            'effective_from'        => ['nullable', 'date'],
        ];
    }
}
