<?php

namespace App\Http\Requests;

use App\Support\DocumentFileValidator;
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
                function ($attribute, $value, $fail) {
                    if ($value && $value->getSize() > 5 * 1024 * 1024) {
                        $fail(sprintf('Arquivo muito grande (máx. 5MB): %s', $value->getClientOriginalName()));
                    }

                    if ($value) {
                        $validationError = DocumentFileValidator::validate($value);

                        if ($validationError !== null) {
                            $fail($validationError);
                        }
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'É obrigatório enviar um arquivo.',
        ];
    }
}
