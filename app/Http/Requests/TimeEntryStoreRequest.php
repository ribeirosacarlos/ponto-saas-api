<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TimeEntryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policies controlam a ação
    }

    public function rules(): array
    {
        return [
            'type'      => 'required|in:in,out',
            'latitude'  => 'nullable|string',
            'longitude' => 'nullable|string',
        ];
    }
}
