<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // será controlado por policy+middleware
    }

    public function rules(): array
    {
        $emailRule = Rule::unique('users', 'email');

        if ($this->route('employee')) {
            $emailRule = $emailRule->ignore($this->route('employee'));
        }

        return [
            'name'     => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'email'    => [($this->isMethod('POST') ? 'required' : 'sometimes'), 'email', $emailRule],
            'password' => [$this->isMethod('POST') ? 'required' : 'nullable', 'min:6'],
            'role'     => 'nullable|string|in:admin,manager,area_manager,employee',
            'shift_id' => 'nullable|uuid|exists:shifts,id',
        ];
    }
}
