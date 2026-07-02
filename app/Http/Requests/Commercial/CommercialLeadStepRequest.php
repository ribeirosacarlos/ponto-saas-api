<?php

namespace App\Http\Requests\Commercial;

use App\Models\CommercialLeadStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialLeadStepRequest extends FormRequest
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
                'nullable', 'string', 'max:255',
                Rule::unique(CommercialLeadStep::class, 'slug')->ignore($this->route('id')),
            ],
            'description' => ['nullable', 'string'],
            'position' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:0'],
            'default_due_days' => ['nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'is_final' => ['sometimes', 'boolean'],
        ];
    }
}
