<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'original_time'  => 'required|date',
            'corrected_time' => 'required|date',
            'reason'         => 'required|string|max:500',
        ];
    }
}
