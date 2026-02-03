<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Models\User;
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

        // Check if target user belongs to the same company
        $targetUser = User::find($this->input('user_id'));
        
        return $targetUser && $targetUser->company_id === $user->company_id;
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
            'user_id.required' => 'O ID do usuário é obrigatório.',
            'user_id.exists' => 'Usuário não encontrado.',
            'category.required' => 'A categoria é obrigatória.',
            'files.required' => 'Pelo menos um arquivo deve ser enviado.',
            'files.array' => 'O campo files precisa ser um array de arquivos.',
            'files.*.required' => 'Cada item enviado precisa ser um arquivo válido.',
            'files.*.file' => 'O arquivo enviado não é válido.',
            'files.*.mimes' => 'As extensões permitidas são: pdf, jpg, jpeg, png, doc, docx, xls e xlsx.',
        ];
    }
}