<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'lead_magnet_type' => ['required', Rule::in(['planilha', 'guia'])],
            'page_slug' => ['nullable', 'string', 'max:255'],
            'consented_at' => ['required', 'date'],
            'honeypot' => ['nullable', 'string'],
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

    public function messages(): array
    {
        return [
            'email.required' => 'Informe um e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'lead_magnet_type.required' => 'Material solicitado inválido.',
            'lead_magnet_type.in' => 'Material solicitado inválido.',
            'consented_at.required' => 'É necessário aceitar os termos para continuar.',
            'consented_at.date' => 'É necessário aceitar os termos para continuar.',
        ];
    }
}
