<?php

namespace App\Http\Requests\Commercial;

use App\Models\CommercialAffiliate;
use App\Models\CommercialCommissionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialAffiliateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        $slugRule = Rule::unique(CommercialAffiliate::class, 'slug');

        if ($this->route('affiliate')) {
            $slugRule = $slugRule->ignore($this->route('affiliate'));
        }

        return [
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'slug' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255', 'alpha_dash', $slugRule],
            'commission_plan_id' => ['nullable', 'uuid', Rule::exists(CommercialCommissionPlan::class, 'id')],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
