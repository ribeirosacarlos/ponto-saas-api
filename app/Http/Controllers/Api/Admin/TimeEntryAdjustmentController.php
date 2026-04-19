<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimeEntryAdjustmentReviewRequest;
use App\Models\TimeEntry;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TimeEntryAdjustmentController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {
    }

    public function approve(TimeEntryAdjustmentReviewRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        $this->authorize('approveAdjustment', $timeEntry);

        if (! $timeEntry->isAdjustmentPending()) {
            return response()->json([
                'message' => 'Não há ajuste pendente para este registro.'
            ], 409);
        }

        $before = $this->timeEntrySnapshot($timeEntry);

        DB::transaction(function () use ($request, $timeEntry) {
            $timeEntry->fill([
                'adjustment_status' => 'approved',
                'adjustment_reviewed_by' => $request->user()->id,
                'adjustment_reviewed_at' => now(),
                'adjustment_review_reason' => $request->review_reason,
            ]);

            $timeEntry->save();
        });

        $timeEntry = $timeEntry->refresh();

        $this->auditLogService->log(
            action: 'time_entry.adjustment_approved',
            entityType: TimeEntry::class,
            entityId: $timeEntry->id,
            description: 'Ajuste de ponto aprovado.',
            oldValues: $before,
            newValues: $this->timeEntrySnapshot($timeEntry),
            companyId: $timeEntry->company_id,
        );

        return response()->json($timeEntry);
    }

    public function reject(TimeEntryAdjustmentReviewRequest $request, TimeEntry $timeEntry): JsonResponse
    {
        $this->authorize('rejectAdjustment', $timeEntry);

        if (! $timeEntry->isAdjustmentPending()) {
            return response()->json([
                'message' => 'Não há ajuste pendente para este registro.'
            ], 409);
        }

        $before = $this->timeEntrySnapshot($timeEntry);

        $timeEntry->update([
            'adjustment_status' => 'rejected',
            'adjustment_reviewed_by' => $request->user()->id,
            'adjustment_reviewed_at' => now(),
            'adjustment_review_reason' => $request->review_reason,
        ]);

        $timeEntry = $timeEntry->refresh();

        $this->auditLogService->log(
            action: 'time_entry.adjustment_rejected',
            entityType: TimeEntry::class,
            entityId: $timeEntry->id,
            description: 'Ajuste de ponto rejeitado.',
            oldValues: $before,
            newValues: $this->timeEntrySnapshot($timeEntry),
            companyId: $timeEntry->company_id,
        );

        return response()->json($timeEntry);
    }

    protected function timeEntrySnapshot(TimeEntry $timeEntry): array
    {
        return $this->auditLogService->snapshot([
            'user_id' => $timeEntry->user_id,
            'clocked_at' => optional($timeEntry->clocked_at)->toIso8601String(),
            'type' => $timeEntry->type,
            'event_kind' => $timeEntry->event_kind,
            'source' => $timeEntry->source,
            'adjustment_status' => $timeEntry->adjustment_status,
            'adjustment_reason' => $timeEntry->adjustment_reason,
            'adjustment_review_reason' => $timeEntry->adjustment_review_reason,
            'adjustment_requested_by' => $timeEntry->adjustment_requested_by,
            'adjustment_reviewed_by' => $timeEntry->adjustment_reviewed_by,
        ]);
    }
}
