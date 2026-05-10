<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Support\DocumentFileValidator;
use App\Services\UserVisibilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminDocumentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user is admin/manager/area_manager
        $user = $this->user();
        $privilegedRoles = ['admin', 'manager', 'area_manager'];
        
        if (! $user || ! $user->hasRole($privilegedRoles)) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return app(UserVisibilityService::class)->canManageUserId($user, $this->input('user_id'));
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
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
            'user_id.required' => 'O ID do usuário é obrigatório.',
            'user_id.exists' => 'Usuário não encontrado.',
            'category.required' => 'A categoria é obrigatória.',
            'files.required' => 'Pelo menos um arquivo deve ser enviado.',
            'files.array' => 'O campo files precisa ser um array de arquivos.',
            'files.*.required' => 'Cada item enviado precisa ser um arquivo válido.',
            'files.*.file' => 'O arquivo enviado não é válido.',
        ];
    }
}
