<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'name' => 'sometimes|string|max:255',
            'slug' => ['sometimes', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($planId)],
            'description' => 'sometimes|nullable|string',
            'price_cents' => 'sometimes|integer|min:0',
            'currency' => 'sometimes|string|size:3',
            'billing_interval' => 'sometimes|nullable|in:month,year,one_time',
            'trial_days' => 'sometimes|nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|nullable|integer|min:0',
            'features' => 'sometimes|array',
            'features.*' => 'nullable',
            'quotas' => 'sometimes|array',
            'quotas.*' => 'nullable',
        ];
    }
}
