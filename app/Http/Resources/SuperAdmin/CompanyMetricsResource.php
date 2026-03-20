<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Resources\Json\JsonResource;

class CompanyMetricsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'email' => $this->email,
            'document' => $this->document,
            'city' => $this->city,
            'state' => $this->state,
            'timezone' => $this->timezone,
            'is_blocked' => (bool) $this->is_blocked,
            'blocked_at' => $this->blocked_at?->toISOString(),
            'subscription_status' => $this->subscription_status,
            'subscription_status_label' => $this->subscription_status_label,
            'plan_name' => $this->plan_name,
            'plan_slug' => $this->plan_slug,
            'plan_price_cents' => $this->plan_price_cents !== null ? (int) $this->plan_price_cents : null,
            'employees_count' => (int) ($this->employees_count ?? 0),
            'active_employees_30d' => (int) ($this->active_employees_30d ?? 0),
            'time_entries_today' => (int) ($this->time_entries_today ?? 0),
            'time_entries_30d' => (int) ($this->time_entries_30d ?? 0),
            'last_activity_at' => $this->last_activity_at,
            'health_status' => $this->health_status,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
