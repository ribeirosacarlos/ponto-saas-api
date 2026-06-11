<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OptOutLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Informe um e-mail.',
            'email.email' => 'Informe um e-mail válido.',
        ];
    }
}
