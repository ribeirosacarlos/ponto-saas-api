<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminAnnouncementUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'summary' => 'nullable|string',
            'body' => 'nullable|string',
            'type' => 'nullable|string|in:general,holiday,vacation,tip',
            'sent_at' => 'nullable|date',
        ];
    }
}
