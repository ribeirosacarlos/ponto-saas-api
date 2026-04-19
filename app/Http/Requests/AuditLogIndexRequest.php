<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'action' => ['sometimes', 'nullable', 'string', 'max:255'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'entity_id' => ['sometimes', 'nullable', 'uuid'],
            'user_id' => ['sometimes', 'nullable', 'uuid'],
            'performed_by_role' => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_id' => ['sometimes', 'nullable', 'uuid'],
            'target_company_id' => ['sometimes', 'nullable', 'uuid'],
            'date_from' => ['sometimes', 'nullable', 'date'],
            'date_to' => ['sometimes', 'nullable', 'date'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
