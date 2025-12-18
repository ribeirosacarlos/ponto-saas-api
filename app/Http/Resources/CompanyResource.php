<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'document' => $this->document,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'plan' => $this->subscription?->plan,
            'trial_ends_at' => $this->subscription?->trial_ends_at?->toDateTimeString(),
            'subscription_ends_at' => $this->subscription?->current_period_end?->toDateTimeString(),
            'is_blocked' => $this->is_blocked,
            'blocked_at' => $this->blocked_at?->toDateTimeString(),
            'blocked_reason' => $this->blocked_reason,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
