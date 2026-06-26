<?php

namespace App\Http\Resources\Commercial;

use Illuminate\Http\Resources\Json\JsonResource;

class CommercialAffiliateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'slug' => $this->slug,
            'referral_url' => url('/r/'.$this->slug),
            'commission_plan_id' => $this->commission_plan_id,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'commission_plan' => $this->whenLoaded('commissionPlan', fn () => [
                'id' => $this->commissionPlan->id,
                'name' => $this->commissionPlan->name,
                'commission_percentage' => $this->commissionPlan->commission_percentage,
                'recurrence_months' => $this->commissionPlan->recurrence_months,
            ]),
        ];
    }
}
