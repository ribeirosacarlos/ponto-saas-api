<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'name' => $this->name,
            'email' => $this->email,
            'area_id' => $this->area_id,
            'must_change_password' => (bool) $this->must_change_password,
            'role' => $this->role,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])->values()),
            'area' => $this->whenLoaded('area', fn () => $this->area ? [
                'id' => $this->area->id,
                'name' => $this->area->name,
            ] : null),
            'managed_areas' => $this->whenLoaded('managedAreas', fn () => $this->managedAreas->map(fn ($area) => [
                'id' => $area->id,
                'name' => $area->name,
            ])->values()),
            'user_shifts' => $this->whenLoaded('userShifts', fn () => $this->userShifts->map(fn ($assignment) => [
                'id' => $assignment->id,
                'shift_id' => $assignment->shift_id,
                'start_date' => $assignment->start_date,
                'end_date' => $assignment->end_date,
                'shift' => $assignment->relationLoaded('shift') && $assignment->shift ? [
                    'id' => $assignment->shift->id,
                    'name' => $assignment->shift->name,
                    'is_default' => (bool) $assignment->shift->is_default,
                ] : null,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
