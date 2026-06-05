<?php

namespace App\Http\Requests;

class AdminStoreMedicalCertificateRequest extends StoreMedicalCertificateRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ]);
    }
}
