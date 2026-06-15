<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

// Resource "passthrough" para paginar itens já transformados (Document + Timesheet) sem reaplicar transformação.
class PendingDocumentItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource;
    }
}
