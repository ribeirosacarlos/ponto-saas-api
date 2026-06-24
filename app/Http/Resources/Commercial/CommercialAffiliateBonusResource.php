<?php

namespace App\Http\Resources\Commercial;

use Illuminate\Http\Resources\Json\JsonResource;

class CommercialAffiliateBonusResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'affiliate_id' => $this->affiliate_id,
            'year' => $this->year,
            'month' => $this->month,
            'clients_count' => $this->clients_count,
            'bonus_every_clients' => $this->bonus_every_clients,
            'bonus_amount' => $this->bonus_amount,
            'total_bonus_amount' => $this->total_bonus_amount,
            'status' => $this->status,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
