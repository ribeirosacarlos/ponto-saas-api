<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'category' => $this->category,
            'status' => $this->status,
            'size_bytes' => $this->size_bytes,
            'mime_type' => $this->mime_type,
            'ext' => $this->ext,
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];

        if ($request->routeIs('documents.show')) {
            $data['view_url'] = route('documents.view', $this->id);
            $data['download_url'] = route('documents.download', $this->id);
        }

        return $data;
    }
}
