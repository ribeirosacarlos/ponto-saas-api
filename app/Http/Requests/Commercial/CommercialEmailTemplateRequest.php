<?php

namespace App\Http\Requests\Commercial;

use App\Models\CommercialEmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => [
                $isCreate ? 'required' : 'sometimes', 'string', 'max:255',
                Rule::unique(CommercialEmailTemplate::class, 'slug')->ignore($this->route('id')),
            ],
            'category' => ['nullable', 'string', 'max:255'],
            'subject' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:255'],
            'body_html' => [$isCreate ? 'required' : 'sometimes', 'string'],
            'body_text' => ['nullable', 'string'],
            'available_variables' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
