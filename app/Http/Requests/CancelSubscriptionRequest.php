<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) $user?->hasRole(['admin', 'super_admin']);
    }

    public function rules(): array
    {
        return [];
    }
}
