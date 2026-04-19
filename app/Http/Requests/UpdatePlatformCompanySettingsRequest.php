<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePlatformCompanySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'timezone' => [
                'sometimes',
                'required',
                'string',
                'max:64',
                Rule::in(timezone_identifiers_list()),
            ],
            'audit_logs_enabled' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->hasAny(['timezone', 'audit_logs_enabled'])) {
                $validator->errors()->add(
                    'settings',
                    'Informe ao menos uma configuração administrativa para atualização.'
                );
            }
        });
    }
}
