<?php

namespace App\Http\Resources\Commercial;

use Illuminate\Http\Resources\Json\JsonResource;

class CommercialCommissionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'affiliate_id' => $this->affiliate_id,
            'lead_id' => $this->lead_id,
            'customer_id' => $this->customer_id,
            'invoice_id' => $this->invoice_id,
            'commission_plan_id' => $this->commission_plan_id,
            'base_amount' => $this->base_amount,
            'commission_percentage' => $this->commission_percentage,
            'commission_amount' => $this->commission_amount,
            'month_number' => $this->month_number,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
