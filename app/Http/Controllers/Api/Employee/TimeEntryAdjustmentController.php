<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeEntryAdjustmentRequest;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class TimeEntryAdjustmentController extends Controller
{
    public function store(TimeEntryAdjustmentRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        $this->authorize('requestAdjustment', $timeEntry);

        $clockedAt = $this->resolveClockedAt($request, $timeEntry);
        $type = $this->resolveAdjustmentType($timeEntry, $clockedAt);
        $adjustment = TimeEntry::create([
            'company_id' => $timeEntry->company_id,
            'user_id' => $timeEntry->user_id,
            'clocked_at' => $clockedAt,
            'type' => $type,
            'latitude' => $request->proposed_latitude ?? $timeEntry->latitude,
            'longitude' => $request->proposed_longitude ?? $timeEntry->longitude,
            'source' => $request->proposed_source ?? 'adjustment',
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

        return response()->json($adjustment, 201);
    }

    private function resolveClockedAt(TimeEntryAdjustmentRequest $request, TimeEntry $timeEntry): Carbon
    {
        $value = $request->proposed_clocked_at ?? $timeEntry->clocked_at;

        return Carbon::parse($value);
    }

    private function resolveAdjustmentType(TimeEntry $timeEntry, Carbon $clockedAt): string
    {
        $lastEntry = TimeEntry::query()
            ->excludeRejected()
            ->where('user_id', $timeEntry->user_id)
            ->where('company_id', $timeEntry->company_id)
            ->whereDate('clocked_at', $clockedAt->toDateString())
            ->where('clocked_at', '<=', $clockedAt)
            ->where('id', '!=', $timeEntry->id)
            ->orderByDesc('clocked_at')
            ->first();

        if (! $lastEntry) {
            return 'in';
        }

        return $this->nextClockType($lastEntry->type);
    }

    private function nextClockType(?string $type): string
    {
        return $type === 'in' ? 'out' : 'in';
    }
}
