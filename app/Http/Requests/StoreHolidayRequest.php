<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'name' => 'required|string|max:255',
            'scope' => 'nullable|string|in:national,regional,local,company',
        ];
    }
}
