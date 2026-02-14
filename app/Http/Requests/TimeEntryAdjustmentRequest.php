<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TimeEntryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proposed_clocked_at' => ['nullable', 'date'],
            'proposed_type' => ['nullable', 'string', 'in:in,out'],
            'proposed_latitude' => ['nullable', 'numeric'],
            'proposed_longitude' => ['nullable', 'numeric'],
            'proposed_source' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('proposed_latitude')) {
            $this->merge(['proposed_latitude' => $this->input('proposed_latitude')]);
        }

        if ($this->filled('proposed_longitude')) {
            $this->merge(['proposed_longitude' => $this->input('proposed_longitude')]);
        }
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hasAnyProposed = collect([
                $this->input('proposed_clocked_at'),
                $this->input('proposed_type'),
                $this->input('proposed_latitude'),
                $this->input('proposed_longitude'),
                $this->input('proposed_source'),
            ])->filter(fn ($value) => ! is_null($value) && $value !== '')->isNotEmpty();

            if (! $hasAnyProposed) {
                $validator->errors()->add('adjustment', 'Informe ao menos um campo proposto para o ajuste.');
            }
        });
    }
}
