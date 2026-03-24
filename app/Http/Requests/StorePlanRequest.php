<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', Rule::unique('plans', 'slug')],
            'description' => 'nullable|string',
            'price_cents' => 'required|integer|min:0',
            'currency' => 'required|string|size:3',
            'billing_interval' => 'nullable|in:month,year,one_time',
            'trial_days' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'features' => 'sometimes|array',
            'features.*' => 'nullable',
            'quotas' => 'sometimes|array',
            'quotas.*' => 'nullable',
            'extra_employee_price_cents' => 'sometimes|nullable|integer|min:0',
            'stripe_price_id' => 'sometimes|nullable|string',
            'stripe_extra_employee_price_id' => 'sometimes|nullable|string',
        ];
    }
}
