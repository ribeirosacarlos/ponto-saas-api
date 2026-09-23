<?php

namespace App\Http\Requests\Commercial;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialLeadAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'assigned_to_user_id' => [
                'required', 'uuid',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    $user = User::find($value);

                    if (! $user || ! $user->hasRole('super_admin')) {
                        $fail('O responsável atribuído precisa ser um super_admin.');
                    }
                },
            ],
        ];
    }
}
