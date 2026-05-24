<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country' => ['sometimes', 'nullable', 'string', 'max:10'],
            'locale'  => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }
}
