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

        $adjustment = TimeEntry::create([
            'company_id' => $timeEntry->company_id,
            'user_id' => $timeEntry->user_id,
            'clocked_at' => $request->proposed_clocked_at ?? $timeEntry->clocked_at,
            'type' => $request->proposed_type ?? $timeEntry->type,
            'latitude' => $request->proposed_latitude ?? $timeEntry->latitude,
            'longitude' => $request->proposed_longitude ?? $timeEntry->longitude,
            'source' => $request->proposed_source ?? $timeEntry->source,
            'adjustment_status' => 'pending',
            'adjustment_reason' => $request->reason,
            'adjustment_requested_by' => $request->user()->id,
            'adjustment_requested_at' => now(),
        ]);

        return response()->json($adjustment, 201);
    }
}
