<?php

namespace App\Http\Resources;

class DocumentAdminResource extends DocumentResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        $data['type'] = 'document';

        $data['employee'] = [
            'id' => $this->user?->id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
        ];

        $data['rejected_comment'] = $this->rejected_comment;
        $data['rejected_by'] = $this->rejected_by;
        $data['rejected_at'] = $this->rejected_at?->toDateTimeString();
        $data['absence'] = $this->absenceContext();

        if (! isset($data['view_url'])) {
            $data['view_url'] = route('documents.view', $this->id);
            $data['download_url'] = route('documents.download', $this->id);
        }

        return $data;
    }

    private function absenceContext(): ?array
    {
        if (! $this->relationLoaded('absences') || $this->absences->isEmpty()) {
            return null;
        }

        $absence = $this->absences->first();

        return [
            'id' => $absence->id,
            'type' => $absence->type,
            'status' => $absence->status,
            'coverage_type' => $absence->coverage_type,
            'start_date' => $absence->start_date?->toDateString(),
            'end_date' => $absence->end_date?->toDateString(),
        ];
    }
}
