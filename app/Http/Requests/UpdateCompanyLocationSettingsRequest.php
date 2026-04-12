<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyLocationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        foreach (['company_latitude', 'company_longitude'] as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $payload[$field] = trim($this->input($field));
            }
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'company_latitude' => ['required', 'numeric', 'between:-90,90'],
            'company_longitude' => ['required', 'numeric', 'between:-180,180'],
            'allowed_radius_meters' => ['required', 'integer', 'min:10', 'max:5000'],
            'location_validation_enabled' => ['required', 'boolean'],
        ];
    }

    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();

        if (array_key_exists('company_latitude', $validated)) {
            $validated['company_latitude'] = number_format((float) $validated['company_latitude'], 7, '.', '');
        }

        if (array_key_exists('company_longitude', $validated)) {
            $validated['company_longitude'] = number_format((float) $validated['company_longitude'], 7, '.', '');
        }

        if ($key !== null) {
            return data_get($validated, $key, $default);
        }

        return $validated;
    }
}
