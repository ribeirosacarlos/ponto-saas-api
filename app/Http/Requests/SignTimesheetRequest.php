<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class SignTimesheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signature_image' => ['required', 'string'],
            'accepted_terms' => ['required', 'boolean', 'accepted'],
            'password' => ['required', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function messages(): array
    {
        return [
            'signature_image.required' => 'A assinatura desenhada é obrigatória.',
            'accepted_terms.required' => 'É necessário aceitar os termos para assinar.',
            'accepted_terms.accepted' => 'Você deve confirmar o aceite dos termos.',
            'password.required' => 'A senha é obrigatória para confirmar a assinatura.',
            'latitude.between' => 'Latitude inválida.',
            'longitude.between' => 'Longitude inválida.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'accepted_terms' => filter_var($this->accepted_terms, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $this->accepted_terms,
        ]);
    }
}
