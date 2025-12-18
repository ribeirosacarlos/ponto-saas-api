<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'billing_interval' => $this->billing_interval,
            'trial_days' => $this->trial_days,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'features' => $this->features ?? [],
            'quotas' => $this->quotas ?? [],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
