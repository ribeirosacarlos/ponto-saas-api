<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanySignatureSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enable_native_signatures'                    => ['sometimes', 'required', 'boolean'],
            'require_timesheet_signature'                 => ['sometimes', 'required', 'boolean'],
            'require_password_confirmation_for_signature' => ['sometimes', 'required', 'boolean'],
            'allow_geolocation_on_signature'              => ['sometimes', 'required', 'boolean'],
        ];
    }
}
