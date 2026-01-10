<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => [
                'required',
                'uuid',
            ],
            'plan_id' => [
                'required',
                'uuid',
                Rule::exists('plans', 'id')->where('is_active', true),
            ],
            'stripe_price_id' => 'nullable|string',
            'honeypot' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('honeypot')) {
                $validator->errors()->add('honeypot', 'Detecção de spam acionada.');
            }
        });
    }
}
