<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['sometimes', 'required', 'string', 'max:255'],
            'email'   => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone'   => ['sometimes', 'nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'state'   => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
