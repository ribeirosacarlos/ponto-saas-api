<?php

namespace App\Http\Resources;

use App\Support\CompanyTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timezone = CompanyTime::resolveTimezone($this->company);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'company_id' => $this->company_id,
            'area_id' => $this->area_id,
            'must_change_password' => (bool) $this->must_change_password,
            'timezone' => $timezone,
            'timeZone' => $timezone,
            'role' => $this->role,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])->values()),
            'company' => $this->whenLoaded('company', fn () => $this->company ? [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'slug' => $this->company->slug,
                'timezone' => $this->company->timezone ?? $timezone,
                'timeZone' => $this->company->timezone ?? $timezone,
                'country' => $this->company->country,
                'locale' => $this->company->locale,
            ] : null),
        ];
    }
}
