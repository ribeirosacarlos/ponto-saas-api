<?php

namespace App\Http\Controllers\Api\Employee;

use App\Actions\TimeEntries\CreateTimeEntryAdjustmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimeEntryAdjustmentRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class TimeEntryAdjustmentController extends Controller
{
    public function store(
        TimeEntryAdjustmentRequest $request,
        TimeEntry $timeEntry,
        CreateTimeEntryAdjustmentAction $createAdjustment
    ): JsonResponse
    {
        $this->authorize('requestAdjustment', $timeEntry);

        $clockedAt = CarbonImmutable::parse($request->proposed_clocked_at ?? $timeEntry->clocked_at);

        $adjustment = $createAdjustment->handle($timeEntry->user, $request->user(), [
            'clocked_at' => $clockedAt,
            'reason' => $request->reason,
            'proposed_clocked_at' => $request->proposed_clocked_at,
            'proposed_type' => $request->proposed_type,
            'proposed_latitude' => $request->proposed_latitude,
            'proposed_longitude' => $request->proposed_longitude,
            'proposed_source' => $request->proposed_source,
            'latitude' => $request->proposed_latitude ?? $timeEntry->latitude,
            'longitude' => $request->proposed_longitude ?? $timeEntry->longitude,
            'source' => $request->proposed_source ?? 'adjustment',
        ]);

        return response()->json((new TimeEntryResource($adjustment))->resolve(), 201);
    }
}
