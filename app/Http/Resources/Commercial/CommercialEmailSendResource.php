<?php

namespace App\Http\Resources\Commercial;

use Illuminate\Http\Resources\Json\JsonResource;

class CommercialEmailSendResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'enrollment_id' => $this->enrollment_id,
            'lead_id' => $this->lead_id,
            'sequence_step_id' => $this->sequence_step_id,
            'template_id' => $this->template_id,
            'to_email' => $this->to_email,
            'rendered_subject' => $this->rendered_subject,
            'resend_message_id' => $this->resend_message_id,
            'status' => $this->status,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'first_clicked_at' => $this->first_clicked_at?->toIso8601String(),
            'bounced_at' => $this->bounced_at?->toIso8601String(),
            'complained_at' => $this->complained_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
            'cancel_reason' => $this->cancel_reason,
            'attempt_count' => $this->attempt_count,
            'lead' => $this->whenLoaded('lead', fn () => [
                'id' => $this->lead->id,
                'company_name' => $this->lead->company_name,
                'contact_name' => $this->lead->contact_name,
                'assigned_to_user_id' => $this->lead->assigned_to_user_id,
            ]),
            'template' => $this->whenLoaded('template', fn () => [
                'id' => $this->template->id,
                'name' => $this->template->name,
            ]),
            'sequence_step' => $this->whenLoaded('sequenceStep', fn () => [
                'id' => $this->sequenceStep->id,
                'position' => $this->sequenceStep->position,
                'name' => $this->sequenceStep->name,
                'sequence' => $this->sequenceStep->relationLoaded('sequence') ? [
                    'id' => $this->sequenceStep->sequence->id,
                    'name' => $this->sequenceStep->sequence->name,
                ] : null,
            ]),
        ];
    }
}
