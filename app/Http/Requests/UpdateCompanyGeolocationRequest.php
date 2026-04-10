<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyGeolocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'required_on_clock' => ['required', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}
