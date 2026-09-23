<?php

namespace App\Http\Resources\Commercial;

use Illuminate\Http\Resources\Json\JsonResource;

class CommercialLeadResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            ...$this->pipelineStatus(),
            'id' => $this->id,
            'company_name' => $this->company_name,
            'contact_name' => $this->contact_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'website' => $this->website,
            'google_maps_place_id' => $this->google_maps_place_id,
            'country' => $this->country,
            'city' => $this->city,
            'segment' => $this->segment,
            'employees_count' => $this->employees_count,
            'source' => $this->source,
            'affiliate_id' => $this->affiliate_id,
            'current_step_id' => $this->current_step_id,
            'current_step_started_at' => $this->current_step_started_at?->toIso8601String(),
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'created_by_user_id' => $this->created_by_user_id,
            'status' => $this->status,
            'priority' => $this->priority,
            'score' => $this->score,
            'general_notes' => $this->general_notes,
            'next_action_type' => $this->next_action_type,
            'next_action_at' => $this->next_action_at?->toIso8601String(),
            'next_action_user_id' => $this->next_action_user_id,
            'converted_at' => $this->converted_at?->toIso8601String(),
            'customer_id' => $this->customer_id,
            'lost_reason' => $this->lost_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'current_step' => $this->whenLoaded('currentStep', fn () => [
                'id' => $this->currentStep->id,
                'name' => $this->currentStep->name,
                'position' => $this->currentStep->position,
            ]),
            'assigned_to_user' => $this->whenLoaded('assignedToUser', fn () => [
                'id' => $this->assignedToUser->id,
                'name' => $this->assignedToUser->name,
                'email' => $this->assignedToUser->email,
            ]),
            'created_by_user' => $this->whenLoaded('createdByUser', fn () => [
                'id' => $this->createdByUser->id,
                'name' => $this->createdByUser->name,
                'email' => $this->createdByUser->email,
            ]),
            'affiliate' => $this->whenLoaded('affiliate', fn () => [
                'id' => $this->affiliate->id,
                'name' => $this->affiliate->name,
                'slug' => $this->affiliate->slug,
            ]),
            'notes' => CommercialLeadNoteResource::collection($this->whenLoaded('notes')),
            'step_logs' => CommercialLeadStepLogResource::collection($this->whenLoaded('stepLogs')),
        ];
    }
}
