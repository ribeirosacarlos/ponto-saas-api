<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentResendRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document && $this->user()?->can('resend', $document);
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx',
                function ($attribute, $value, $fail) {
                    if ($value && $value->getSize() > 5 * 1024 * 1024) {
                        $fail(sprintf('Arquivo muito grande (máx. 5MB): %s', $value->getClientOriginalName()));
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'É obrigatório enviar um arquivo.',
            'file.mimes' => 'As extensões permitidas são: pdf, jpg, jpeg, png, doc, docx, xls e xlsx.',
        ];
    }
}
