<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialOutreachSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_globally_paused' => ['sometimes', 'boolean'],
            'pause_reason' => ['nullable', 'string', 'max:255'],
            'daily_send_limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'monthly_send_limit' => ['sometimes', 'integer', 'min:1', 'max:3000'],
            'sending_window_start_time' => ['sometimes', 'date_format:H:i'],
            'sending_window_end_time' => ['sometimes', 'date_format:H:i', 'after:sending_window_start_time'],
            'sending_days' => ['sometimes', 'array', 'min:1'],
            'sending_days.*' => [Rule::in(['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'])],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'min_gap_seconds_between_sends' => ['sometimes', 'integer', 'min:0'],
            'max_sends_per_dispatch_run' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'bounce_soft_threshold' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
