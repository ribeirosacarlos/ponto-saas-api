<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class TimeEntryResource extends JsonResource
{
    /**
     * @param  iterable<int, mixed>  $entries
     * @return array<int, array<string, mixed>>
     */
    public static function collectionArray(iterable $entries): array
    {
        return Collection::make($entries)
            ->map(fn ($entry) => (new self($entry))->resolve())
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $payload = [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'user_id' => $this->user_id,
            'user_shift_id' => $this->user_shift_id,
            'clocked_at' => $this->clocked_at?->toIso8601String(),
            'type' => $this->type,
            'event_kind' => $this->event_kind,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'source' => $this->source,
            'device_type' => $this->device_type,
            'adjustment_status' => $this->adjustment_status,
            'adjustment_reason' => $this->adjustment_reason,
            'adjustment_requested_by' => $this->adjustment_requested_by,
            'adjustment_requested_at' => $this->adjustment_requested_at?->toIso8601String(),
            'proposed_clocked_at' => $this->proposed_clocked_at?->toIso8601String(),
            'proposed_type' => $this->proposed_type,
            'proposed_latitude' => $this->proposed_latitude,
            'proposed_longitude' => $this->proposed_longitude,
            'proposed_source' => $this->proposed_source,
            'adjustment_reviewed_by' => $this->adjustment_reviewed_by,
            'adjustment_reviewed_at' => $this->adjustment_reviewed_at?->toIso8601String(),
            'adjustment_review_reason' => $this->adjustment_review_reason,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
        ];

        if ($this->resource->offsetExists('work_date')) {
            $payload['work_date'] = $this->resource->getAttribute('work_date');
        }

        if ($this->resource->offsetExists('day_summary')) {
            $payload['day_summary'] = $this->resource->getAttribute('day_summary');
        }

        return $payload;
    }
}
