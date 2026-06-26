<?php

namespace App\Http\Requests\Commercial;

use App\Models\CommercialAffiliate;
use App\Models\CommercialLeadStep;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialLeadStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Controlado pela policy no controller
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'segment' => ['nullable', 'string', 'max:255'],
            'employees_count' => ['nullable', 'integer', 'min:0'],
            'source' => ['nullable', 'string', 'max:255'],
            'affiliate_id' => ['nullable', 'uuid', Rule::exists(CommercialAffiliate::class, 'id')],
            'current_step_id' => ['nullable', 'uuid', Rule::exists(CommercialLeadStep::class, 'id')],
            'assigned_to_user_id' => [
                'nullable', 'uuid',
                Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    if (! $value) {
                        return;
                    }

                    $user = User::find($value);

                    if (! $user || ! $user->hasRole(['super_admin', 'commercial_manager', 'commercial_agent'])) {
                        $fail('O responsável atribuído precisa ser um usuário comercial interno.');
                    }
                },
            ],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'very_high'])],
            'status' => ['nullable', 'string', Rule::in(['new', 'in_progress', 'demo_scheduled', 'proposal_sent', 'won', 'lost', 'nurturing'])],
            'general_notes' => ['nullable', 'string'],
        ];
    }
}
