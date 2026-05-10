<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Support\DocumentFileValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'string', Rule::in(Document::CATEGORIES)],
            'title' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => [
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
            'files.required' => 'Pelo menos um arquivo deve ser enviado.',
            'files.array' => 'O campo files precisa ser um array de arquivos.',
            'files.*.required' => 'Cada item enviado precisa ser um arquivo válido.',
            'files.*.file' => 'O arquivo enviado não é válido.',
        ];
    }
}
