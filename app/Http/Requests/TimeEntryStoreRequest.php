<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TimeEntryIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'      => ['nullable', 'in:in,out'],
            'user_id'   => ['nullable', 'uuid'],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date'],

            'page'      => ['nullable', 'integer', 'min:1'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }
}
