<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;

class TimeEntryController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {
    }

    public function destroy(string $timeEntry): JsonResponse
    {
        $entry = TimeEntry::query()
            ->where('company_id', request()->user()->company_id)
            ->findOrFail($timeEntry);

        $this->authorize('delete', $entry);

        $snapshot = $this->timeEntrySnapshot($entry);

        $entry->delete();

        $this->auditLogService->log(
            action: 'time_record.deleted',
            entityType: TimeEntry::class,
            entityId: $entry->id,
            description: 'Registro de ponto excluído manualmente.',
            oldValues: $snapshot,
            metadata: [
                'employee_user_id' => $entry->user_id,
                'time_record_id' => $entry->id,
                'original_clocked_at' => optional($entry->clocked_at)->toIso8601String(),
                'type' => $entry->type,
                'event_kind' => $entry->event_kind,
                'technical_reason' => 'exclusão manual por admin/gestor',
            ],
            companyId: $entry->company_id,
        );

        return response()->json([
            'message' => 'Registro de ponto excluído com sucesso.',
        ]);
    }

    private function timeEntrySnapshot(TimeEntry $timeEntry): array
    {
        return $this->auditLogService->snapshot([
            'company_id' => $timeEntry->company_id,
            'user_id' => $timeEntry->user_id,
            'clocked_at' => optional($timeEntry->clocked_at)->toIso8601String(),
            'type' => $timeEntry->type,
            'event_kind' => $timeEntry->event_kind,
            'source' => $timeEntry->source,
            'user_shift_id' => $timeEntry->user_shift_id,
            'adjustment_status' => $timeEntry->adjustment_status,
        ]);
    }
}
