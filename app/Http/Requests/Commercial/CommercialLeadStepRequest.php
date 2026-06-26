<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;

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
            'description' => ['nullable', 'string'],
            'position' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:0'],
            'default_due_days' => ['nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
