<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimesheetSignatureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role->value,
            'signer' => $this->whenLoaded('signer', fn () => [
                'id' => $this->signer->id,
                'name' => $this->signer->name,
            ]),
            'signed_at' => $this->signed_at?->toIso8601String(),
            'ip_address' => $this->ip_address,
        ];
    }
}
