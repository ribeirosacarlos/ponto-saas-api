<?php

namespace App\Http\Resources\Commercial;

use Illuminate\Http\Resources\Json\JsonResource;

class CommercialOutreachSettingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'is_globally_paused' => $this->is_globally_paused,
            'paused_at' => $this->paused_at?->toIso8601String(),
            'pause_reason' => $this->pause_reason,
            'daily_send_limit' => $this->daily_send_limit,
            'monthly_send_limit' => $this->monthly_send_limit,
            'sending_window_start_time' => $this->sending_window_start_time,
            'sending_window_end_time' => $this->sending_window_end_time,
            'sending_days' => $this->sending_days,
            'timezone' => $this->timezone,
            'min_gap_seconds_between_sends' => $this->min_gap_seconds_between_sends,
            'max_sends_per_dispatch_run' => $this->max_sends_per_dispatch_run,
            'bounce_soft_threshold' => $this->bounce_soft_threshold,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
