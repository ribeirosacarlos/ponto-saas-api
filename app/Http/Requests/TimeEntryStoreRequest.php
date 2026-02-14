<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TimeEntryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}
