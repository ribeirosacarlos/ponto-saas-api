<?php

namespace App\Http\Requests\Commercial;

use App\Models\CommercialEmailSequence;
use App\Models\CommercialLead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialEmailEnrollmentStoreRequest extends FormRequest
{
    public const MAX_LEADS = 200;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sequence_id' => ['required', 'uuid', Rule::exists(CommercialEmailSequence::class, 'id')],
            'lead_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_LEADS],
            'lead_ids.*' => ['required', 'uuid', Rule::exists(CommercialLead::class, 'id')],
        ];
    }
}
