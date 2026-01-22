<?php

namespace App\Http\Requests;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        if (! $document instanceof Document) {
            return false;
        }

        $user = $this->user();

        if (! $user) {
            return false;
        }

        $privileged = ['admin', 'manager', 'area_manager'];

        return $user->company_id === $document->company_id
            && $user->hasRole($privileged);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:180'],
            'category' => ['sometimes', 'string', Rule::in(Document::CATEGORIES)],
            'status' => ['sometimes', 'string', Rule::in(Document::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
