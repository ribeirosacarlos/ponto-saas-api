<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeWorkedTodayResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'date' => $this->resource['date'],
            'worked_seconds' => $this->resource['worked_seconds'],
            'worked_minutes' => $this->resource['worked_minutes'],
            'worked_hours_decimal' => $this->resource['worked_hours_decimal'],
            'expected_break_minutes' => $this->resource['expected_break_minutes'],
            'break_seconds_deducted' => $this->resource['break_seconds_deducted'],
            'open_session' => $this->resource['open_session'],
            'details' => [
                'pairs' => $this->resource['details']['pairs'] ?? [],
                'entries' => $this->resource['details']['entries'] ?? [],
            ],
        ];
    }
}
