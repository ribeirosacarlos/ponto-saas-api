<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyTimezoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'timezone' => [
                'required',
                'string',
                'max:64',
                Rule::in(timezone_identifiers_list()),
            ],
        ];
    }
}
