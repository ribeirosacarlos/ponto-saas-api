<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeEntryAdjustmentRequest;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;

class TimeEntryAdjustmentController extends Controller
{
    public function store(TimeEntryAdjustmentRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        $this->authorize('requestAdjustment', $timeEntry);

        if ($timeEntry->isAdjustmentPending()) {
            return response()->json([
                'message' => 'Já existe um ajuste pendente para este registro.'
            ], 409);
        }

        $timeEntry->update([
            'adjustment_status' => 'pending',
            'adjustment_reason' => $request->reason,
            'adjustment_requested_by' => $request->user()->id,
            'adjustment_requested_at' => now(),
            'proposed_clocked_at' => $request->proposed_clocked_at,
            'proposed_type' => $request->proposed_type,
            'proposed_latitude' => $request->proposed_latitude,
            'proposed_longitude' => $request->proposed_longitude,
            'proposed_source' => $request->proposed_source,
        ]);

        return response()->json($timeEntry->refresh(), 201);
    }
}
