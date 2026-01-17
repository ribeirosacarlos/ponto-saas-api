<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class OvertimeReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by controller policies/middleware.
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date', 'before_or_equal:to'],
            'to' => ['required', 'date'],
            'include_days' => ['nullable', 'in:0,1'],
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

            if ($fromDate->diffInDays($toDate) > 365) {
                $validator->errors()->add('to', 'Intervalo máximo permitido é de 366 dias.');
            }
        });
    }
}
