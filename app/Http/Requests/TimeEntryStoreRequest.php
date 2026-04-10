<?php

namespace App\Http\Requests;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;

class TimeEntryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $geolocationRequired = $this->isGeolocationRequired();

        return [
            'latitude' => [
                $geolocationRequired ? 'required' : 'nullable',
                'numeric',
                'between:-90,90',
                'required_with:longitude',
            ],
            'longitude' => [
                $geolocationRequired ? 'required' : 'nullable',
                'numeric',
                'between:-180,180',
                'required_with:latitude',
            ],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $payload = [];

        if ($this->has('latitude')) {
            $payload['latitude'] = is_string($this->input('latitude'))
                ? trim($this->input('latitude'))
                : $this->input('latitude');
        }

        if ($this->has('longitude')) {
            $payload['longitude'] = is_string($this->input('longitude'))
                ? trim($this->input('longitude'))
                : $this->input('longitude');
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function validated($key = null, $default = null): mixed
    {
        $validated = parent::validated();

        if (array_key_exists('latitude', $validated) && $validated['latitude'] !== null) {
            $validated['latitude'] = number_format((float) $validated['latitude'], 6, '.', '');
        }

        if (array_key_exists('longitude', $validated) && $validated['longitude'] !== null) {
            $validated['longitude'] = number_format((float) $validated['longitude'], 6, '.', '');
        }

        if ($key !== null) {
            return data_get($validated, $key, $default);
        }

        return $validated;
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'A latitude e obrigatoria para registrar o ponto.',
            'longitude.required' => 'A longitude e obrigatoria para registrar o ponto.',
            'latitude.required_with' => 'Informe latitude e longitude juntas.',
            'longitude.required_with' => 'Informe latitude e longitude juntas.',
            'latitude.numeric' => 'A latitude deve ser numerica.',
            'longitude.numeric' => 'A longitude deve ser numerica.',
            'latitude.between' => 'A latitude deve estar entre -90 e 90.',
            'longitude.between' => 'A longitude deve estar entre -180 e 180.',
        ];
    }

    private function isGeolocationRequired(): bool
    {
        $company = $this->user()?->company;

        if (! $company) {
            return false;
        }

        $company->loadMissing(['currentPlan', 'subscription.plan']);

        /** @var Plan|null $plan */
        $plan = $company->currentPlan ?? $company->subscription?->plan;

        return (bool) ($plan?->hasFeature('geolocation') ?? false);
    }
}
