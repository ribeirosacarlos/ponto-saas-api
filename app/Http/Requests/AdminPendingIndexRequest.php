<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPendingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['admin', 'manager', 'area_manager']) === true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(Document::CATEGORIES)],
            'employee_id' => ['nullable', 'string', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'regex:/^updated_at:(asc|desc)$/i'],
        ];
    }
}
