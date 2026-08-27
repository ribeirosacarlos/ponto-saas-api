<?php

namespace App\Http\Requests\Commercial;

use App\Models\CommercialEmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialEmailSequenceStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('POST');

        return [
            'template_id' => [$isCreate ? 'required' : 'sometimes', 'uuid', Rule::exists(CommercialEmailTemplate::class, 'id')],
            'position' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:0'],
            'name' => ['nullable', 'string', 'max:255'],
            'delay_days' => [$isCreate ? 'required' : 'sometimes', 'integer', 'min:0'],
            'send_time_override' => ['nullable', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
