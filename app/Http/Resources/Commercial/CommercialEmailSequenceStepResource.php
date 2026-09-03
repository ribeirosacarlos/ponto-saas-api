<?php

namespace App\Http\Resources\Commercial;

use Illuminate\Http\Resources\Json\JsonResource;

class CommercialEmailSequenceStepResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'sequence_id' => $this->sequence_id,
            'template_id' => $this->template_id,
            'position' => $this->position,
            'name' => $this->name,
            'delay_days' => $this->delay_days,
            'send_time_override' => $this->send_time_override,
            'is_active' => $this->is_active,
            'moves_lead_to_step_id' => $this->moves_lead_to_step_id,
            'template' => $this->whenLoaded('template', fn () => [
                'id' => $this->template->id,
                'name' => $this->template->name,
                'slug' => $this->template->slug,
            ]),
            'moves_lead_to_step' => $this->whenLoaded('movesLeadToStep', fn () => $this->movesLeadToStep ? [
                'id' => $this->movesLeadToStep->id,
                'name' => $this->movesLeadToStep->name,
                'slug' => $this->movesLeadToStep->slug,
            ] : null),
        ];
    }
}
