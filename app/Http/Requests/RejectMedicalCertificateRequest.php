<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectMedicalCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
