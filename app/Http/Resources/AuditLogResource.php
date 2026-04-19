<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'entity_type' => $this->entity_type,
            'entity_label' => class_basename((string) $this->entity_type),
            'entity_id' => $this->entity_id,
            'company_id' => $this->company_id,
            'target_company_id' => $this->target_company_id,
            'performed_by_role' => $this->performed_by_role,
            'method' => $this->method,
            'route' => $this->route,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'actor' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'company' => $this->whenLoaded('company', fn () => $this->company ? [
                'id' => $this->company->id,
                'name' => $this->company->name,
                'slug' => $this->company->slug,
            ] : null),
            'target_company' => $this->whenLoaded('targetCompany', fn () => $this->targetCompany ? [
                'id' => $this->targetCompany->id,
                'name' => $this->targetCompany->name,
                'slug' => $this->targetCompany->slug,
            ] : null),
        ];
    }
}
