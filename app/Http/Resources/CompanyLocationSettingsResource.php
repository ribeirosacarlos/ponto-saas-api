<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyLocationSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'company_latitude' => $this->company_latitude,
            'company_longitude' => $this->company_longitude,
            'allowed_radius_meters' => (int) $this->allowed_radius_meters,
            'location_validation_enabled' => (bool) $this->location_validation_enabled,
            'is_configured' => filled($this->company_latitude) && filled($this->company_longitude),
        ];
    }
}
