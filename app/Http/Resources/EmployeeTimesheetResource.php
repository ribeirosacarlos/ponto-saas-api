<?php

namespace App\Http\Resources;

use App\Enums\DisputeStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeTimesheetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $openDispute = $this->whenLoaded('disputes', function () {
            $dispute = $this->disputes->firstWhere('status.value', DisputeStatus::OPEN->value);

            return $dispute ? new TimesheetDisputeResource($dispute) : null;
        });

        return [
            'id' => $this->id,
            'closure_id' => $this->monthly_closure_id,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
            ]),
            'status' => $this->status->value,
            'snapshot' => $this->snapshot,
            'snapshot_generated_at' => $this->snapshot_generated_at?->toIso8601String(),
            'signatures' => TimesheetSignatureResource::collection($this->whenLoaded('signatures')),
            'open_dispute' => $openDispute,
            'pdf_path' => $this->pdf_path,
            'pdf_generated_at' => $this->pdf_generated_at?->toIso8601String(),
            'document_hash' => $this->document_hash,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
