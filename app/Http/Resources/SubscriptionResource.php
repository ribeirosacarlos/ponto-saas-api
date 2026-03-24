<?php

namespace App\Http\Resources;

use App\Http\Resources\PlanResource;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'plan' => new PlanResource($this->plan),
            'status' => $this->status?->value ?? $this->status,
            'trial_ends_at' => $this->trial_ends_at?->toDateTimeString(),
            'current_period_start' => $this->current_period_start?->toDateTimeString(),
            'current_period_end' => $this->current_period_end?->toDateTimeString(),
            'cancel_at_period_end' => $this->cancel_at_period_end,
            'canceled_at' => $this->canceled_at?->toDateTimeString(),
            'past_due_since' => $this->past_due_since?->toDateTimeString(),
            'grace_period_days' => $this->grace_period_days,
            'stripe_customer_id' => $this->stripe_customer_id,
            'stripe_subscription_id' => $this->stripe_subscription_id,
            'stripe_price_id' => $this->stripe_price_id,
            'stripe_subscription_item_id' => $this->stripe_subscription_item_id,
            'stripe_extra_subscription_item_id' => $this->stripe_extra_subscription_item_id,
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
