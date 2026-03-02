<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeTimeEntryHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $from = $this->input('from');
            $to = $this->input('to');

            if (! $from || ! $to) {
                return;
            }

            try {
                $fromDate = CarbonImmutable::parse($from);
                $toDate = CarbonImmutable::parse($to);
            } catch (\Throwable) {
                return;
            }

            if ($fromDate->greaterThan($toDate)) {
                $validator->errors()->add('from', 'A data inicial deve ser menor ou igual a data final.');
            }

            if ($fromDate->diffInDays($toDate) > 365) {
                $validator->errors()->add('to', 'Intervalo máximo permitido é de 366 dias.');
            }
        });
    }
}
