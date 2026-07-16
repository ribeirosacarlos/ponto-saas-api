<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;

class CommercialLeadBulkStoreRequest extends FormRequest
{
    public const MAX_ITEMS = 100;

    public function authorize(): bool
    {
        return true; // Controlado pela policy no controller
    }

    public function rules(): array
    {
        $rules = [
            'leads' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
        ];

        foreach (CommercialLeadStoreRequest::itemRules() as $field => $fieldRules) {
            $rules["leads.*.{$field}"] = $fieldRules;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'leads.max' => 'É possível cadastrar no máximo '.self::MAX_ITEMS.' leads por requisição.',
        ];
    }
}
