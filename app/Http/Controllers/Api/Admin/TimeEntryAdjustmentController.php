<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeEntryAdjustmentReviewRequest;
use App\Models\TimeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TimeEntryAdjustmentController extends Controller
{
    public function approve(TimeEntryAdjustmentReviewRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        $this->authorize('approveAdjustment', $timeEntry);

        if (! $timeEntry->isAdjustmentPending()) {
            return response()->json([
                'message' => 'Não há ajuste pendente para este registro.'
            ], 409);
        }

        DB::transaction(function () use ($request, $timeEntry) {
            $timeEntry->applyApprovedAdjustment();

            $timeEntry->fill([
                'adjustment_status' => 'approved',
                'adjustment_reviewed_by' => $request->user()->id,
                'adjustment_reviewed_at' => now(),
                'adjustment_review_reason' => $request->review_reason,
            ]);

            $timeEntry->save();
        });

        return response()->json($timeEntry->refresh());
    }

    public function reject(TimeEntryAdjustmentReviewRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        $this->authorize('rejectAdjustment', $timeEntry);

        if (! $timeEntry->isAdjustmentPending()) {
            return response()->json([
                'message' => 'Não há ajuste pendente para este registro.'
            ], 409);
        }

        $timeEntry->update([
            'adjustment_status' => 'rejected',
            'adjustment_reviewed_by' => $request->user()->id,
            'adjustment_reviewed_at' => now(),
            'adjustment_review_reason' => $request->review_reason,
        ]);

        return response()->json($timeEntry->refresh());
    }
}
