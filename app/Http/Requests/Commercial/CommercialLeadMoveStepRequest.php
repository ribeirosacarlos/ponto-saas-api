<?php

namespace App\Http\Requests\Commercial;

use App\Models\CommercialLeadStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialLeadMoveStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'step_id' => ['required', 'uuid', Rule::exists(CommercialLeadStep::class, 'id')],
            'note' => ['nullable', 'string'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
