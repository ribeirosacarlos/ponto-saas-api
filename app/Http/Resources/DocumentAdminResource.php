<?php

namespace App\Http\Resources;

class DocumentAdminResource extends DocumentResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        $data['employee'] = [
            'id' => $this->user?->id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
        ];

        $data['rejected_comment'] = $this->rejected_comment;
        $data['rejected_by'] = $this->rejected_by;
        $data['rejected_at'] = $this->rejected_at?->toDateTimeString();

        if (! isset($data['view_url'])) {
            $data['view_url'] = route('documents.view', $this->id);
            $data['download_url'] = route('documents.download', $this->id);
        }

        return $data;
    }
}
