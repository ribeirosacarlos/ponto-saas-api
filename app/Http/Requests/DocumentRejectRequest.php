<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(['admin', 'manager', 'area_manager']) === true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => 'O comentário da rejeição é obrigatório.',
            'comment.min' => 'O comentário precisa ter pelo menos 5 caracteres.',
        ];
    }
}
