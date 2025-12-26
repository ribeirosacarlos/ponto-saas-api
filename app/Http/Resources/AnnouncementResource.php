<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    protected function bodyValue(): ?string
    {
        return $this->body;
    }

    protected function readRecord()
    {
        if (! $this->relationLoaded('reads')) {
            return null;
        }

        return $this->reads->first();
    }

    protected function seenAtTimestamp(): ?string
    {
        $seenAt = $this->readRecord()?->seen_at;

        return $seenAt?->toIso8601String();
    }

    protected function sentAtTimestamp(): ?string
    {
        return $this->sent_at?->toIso8601String()
            ?? $this->created_at?->toIso8601String();
    }

    protected function senderRole(): ?string
    {
        return $this->creator?->roles?->first()?->name;
    }

    public function toArray($request): array
    {
        $seenAt = $this->readRecord()?->seen_at;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'body' => $this->bodyValue(),
            'sentAt' => $this->sentAtTimestamp(),
            'seenAt' => $this->seenAtTimestamp(),
            'status' => $seenAt ? 'seen' : 'pending',
            'senderName' => $this->creator?->name,
            'senderRole' => $this->senderRole(),
        ];
    }
}
