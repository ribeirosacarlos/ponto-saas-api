<?php

namespace App\Http\Requests\Commercial;

use App\Models\CommercialAffiliate;
use App\Models\CommercialCommissionPlan;
use App\Models\User;
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
        $createAccount = (bool) $this->input('create_account', false);

        $slugRule = Rule::unique(CommercialAffiliate::class, 'slug');

        if ($this->route('affiliate')) {
            $slugRule = $slugRule->ignore($this->route('affiliate'));
        }

        $emailAffiliate = Rule::unique(CommercialAffiliate::class, 'email');

        if ($this->route('affiliate')) {
            $emailAffiliate = $emailAffiliate->ignore($this->route('affiliate'));
        }

        $emailRules = [$isCreate ? 'required' : 'sometimes', 'email', 'max:255', $emailAffiliate];

        if ($isCreate && $createAccount) {
            $emailRules[] = Rule::unique(User::class, 'email');
        }

        return [
            'name'               => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'email'              => $emailRules,
            'phone'              => ['nullable', 'string', 'max:50'],
            'slug'               => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255', 'alpha_dash', $slugRule],
            'commission_plan_id' => ['nullable', 'uuid', Rule::exists(CommercialCommissionPlan::class, 'id')],
            'status'             => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'create_account'     => ['sometimes', 'boolean'],
        ];
    }
}
