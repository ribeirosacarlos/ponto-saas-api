<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TimesheetSignaturePendingResource extends JsonResource
{
    public function toArray($request): array
    {
        $closure = $this->monthlyClosure;

        return [
            'type' => 'timesheet_signature',
            'id' => $this->id,
            'title' => $closure
                ? sprintf('Folha de ponto - %02d/%d', $closure->reference_month, $closure->reference_year)
                : 'Folha de ponto',
            'status' => $this->status->value,
            'employee' => [
                'id' => $this->employee?->id,
                'name' => $this->employee?->name,
                'email' => $this->employee?->email,
            ],
            'monthly_closure' => [
                'id' => $this->monthly_closure_id,
                'reference_month' => $closure?->reference_month,
                'reference_year' => $closure?->reference_year,
            ],
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
