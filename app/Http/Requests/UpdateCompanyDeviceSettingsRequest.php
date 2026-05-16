<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyDeviceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allow_mobile_clock'  => ['required', 'boolean'],
            'allow_desktop_clock' => ['required', 'boolean'],
        ];
    }
}
