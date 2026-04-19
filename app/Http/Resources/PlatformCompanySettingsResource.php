<?php

namespace App\Http\Resources;

use App\Support\CompanyTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlatformCompanySettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'company' => [
                'id' => $this->id,
                'name' => $this->name,
                'slug' => $this->slug,
            ],
            'timezone' => $this->timezone,
            'audit_logs_enabled' => (bool) $this->audit_logs_enabled,
            'available_timezones' => CompanyTime::availableTimezones(),
        ];
    }
}
