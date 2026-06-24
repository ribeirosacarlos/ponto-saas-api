<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialLeadNextActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'next_action_type' => ['required', 'string', 'max:255'],
            'next_action_at' => ['required', 'date'],
            'next_action_user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')],
        ];
    }
}
