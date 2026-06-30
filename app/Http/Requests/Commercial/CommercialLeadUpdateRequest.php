<?php

namespace App\Http\Requests\Commercial;

use App\Models\CommercialAffiliate;
use App\Models\CommercialLeadStep;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialLeadUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:50'],
            'website' => ['sometimes', 'nullable', 'string', 'max:255'],
            'country' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'segment' => ['sometimes', 'nullable', 'string', 'max:255'],
            'employees_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'affiliate_id' => ['sometimes', 'nullable', 'uuid', Rule::exists(CommercialAffiliate::class, 'id')],
            'current_step_id' => ['sometimes', 'nullable', 'uuid', Rule::exists(CommercialLeadStep::class, 'id')],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'very_high'])],
            'status' => ['sometimes', 'string', Rule::in(['new', 'in_progress', 'demo_scheduled', 'proposal_sent', 'won', 'lost', 'nurturing'])],
            'score' => ['sometimes', 'integer', 'min:0'],
            'general_notes' => ['sometimes', 'nullable', 'string'],
            'assigned_to_user_id' => [
                'sometimes', 'nullable', 'uuid',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }

                    $user = User::find($value);

                    if (! $user || ! $user->hasRole(['super_admin', 'admin'])) {
                        $fail('O responsável atribuído precisa ser um administrador.');
                    }
                },
            ],
        ];
    }
}
