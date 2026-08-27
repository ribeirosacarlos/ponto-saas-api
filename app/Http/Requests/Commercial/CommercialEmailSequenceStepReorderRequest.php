<?php

namespace App\Http\Requests\Commercial;

use App\Models\CommercialEmailSequenceStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialEmailSequenceStepReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.id' => ['required', 'uuid', Rule::exists(CommercialEmailSequenceStep::class, 'id')],
            'steps.*.position' => ['required', 'integer', 'min:0'],
        ];
    }
}
