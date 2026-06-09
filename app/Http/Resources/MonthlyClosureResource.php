<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MonthlyClosureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'closed_by' => $this->whenLoaded('closedBy', fn () => [
                'id' => $this->closedBy->id,
                'name' => $this->closedBy->name,
            ]),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee?->id,
                'name' => $this->employee?->name,
            ]),
            'employee_id' => $this->employee_id,
            'reference_year' => $this->reference_year,
            'reference_month' => $this->reference_month,
            'status' => $this->status->value,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'timesheets_count' => $this->whenCounted('timesheets'),
            'completed_count' => $this->when(
                $this->relationLoaded('timesheets'),
                fn () => $this->timesheets->where('status.value', 'completed')->count()
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
