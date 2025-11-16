<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'  => 'nullable|string|max:255',
            'email' => 'nullable|email',
            'password' => 'nullable|min:6',
            'role'     => 'nullable|string|in:admin,manager,area_manager,employee'
        ];
    }
}
