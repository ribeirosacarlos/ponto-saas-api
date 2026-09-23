<?php

namespace App\Http\Resources\Commercial;

use Illuminate\Http\Resources\Json\JsonResource;

class CommercialEmailSequenceEnrollmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'lead_id' => $this->lead_id,
            'sequence_id' => $this->sequence_id,
            'status' => $this->status,
            'exit_reason' => $this->exit_reason,
            'current_step_id' => $this->current_step_id,
            'next_step_id' => $this->next_step_id,
            'next_send_at' => $this->next_send_at?->toIso8601String(),
            'enrolled_at' => $this->enrolled_at?->toIso8601String(),
            'enrolled_by_user_id' => $this->enrolled_by_user_id,
            'paused_at' => $this->paused_at?->toIso8601String(),
            'pause_reason' => $this->pause_reason,
            'replied_at' => $this->replied_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'lead' => $this->whenLoaded('lead', fn () => [
                'id' => $this->lead->id,
                'company_name' => $this->lead->company_name,
                'contact_name' => $this->lead->contact_name,
                'email' => $this->lead->email,
                'assigned_to_user_id' => $this->lead->assigned_to_user_id,
            ]),
            'sequence' => $this->whenLoaded('sequence', fn () => [
                'id' => $this->sequence->id,
                'name' => $this->sequence->name,
            ]),
            'current_step' => $this->whenLoaded('currentStep', fn () => $this->currentStep ? [
                'id' => $this->currentStep->id,
                'name' => $this->currentStep->name,
                'position' => $this->currentStep->position,
            ] : null),
            'next_step' => $this->whenLoaded('nextStep', fn () => $this->nextStep ? [
                'id' => $this->nextStep->id,
                'name' => $this->nextStep->name,
                'position' => $this->nextStep->position,
            ] : null),
            'sends' => CommercialEmailSendResource::collection($this->whenLoaded('sends')),
        ];
    }
}
