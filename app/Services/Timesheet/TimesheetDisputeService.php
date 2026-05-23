<?php

namespace App\Services\Timesheet;

use App\Enums\DisputeStatus;
use App\Enums\TimesheetStatus;
use App\Models\EmployeeTimesheet;
use App\Models\TimesheetDispute;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimesheetDisputeService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function dispute(EmployeeTimesheet $timesheet, User $employee, string $reason): TimesheetDispute
    {
        if ((string) $employee->id !== (string) $timesheet->employee_id) {
            abort(403, 'Você só pode contestar a própria folha.');
        }

        if ($timesheet->status !== TimesheetStatus::PENDING_EMPLOYEE) {
            throw ValidationException::withMessages([
                'status' => 'A folha não pode ser contestada no status atual.',
            ]);
        }

        if ($timesheet->hasOpenDispute()) {
            throw ValidationException::withMessages([
                'dispute' => 'Já existe uma contestação em aberto para esta folha.',
            ]);
        }

        $dispute = DB::transaction(function () use ($timesheet, $employee, $reason) {
            $dispute = TimesheetDispute::create([
                'company_id' => $timesheet->company_id,
                'employee_timesheet_id' => $timesheet->id,
                'employee_id' => $employee->id,
                'reason' => $reason,
                'status' => DisputeStatus::OPEN->value,
            ]);

            $timesheet->update(['status' => TimesheetStatus::DISPUTED->value]);

            return $dispute;
        });

        $this->auditLogService->log(
            action: 'timesheet.disputed',
            entityType: EmployeeTimesheet::class,
            entityId: $timesheet->id,
            description: 'Folha contestada pelo colaborador',
            newValues: ['reason' => $reason],
            companyId: $timesheet->company_id,
        );

        return $dispute;
    }

    public function resolve(TimesheetDispute $dispute, User $resolver, string $resolutionNote): TimesheetDispute
    {
        if ($dispute->status !== DisputeStatus::OPEN) {
            throw ValidationException::withMessages([
                'status' => 'Esta contestação já foi resolvida.',
            ]);
        }

        DB::transaction(function () use ($dispute, $resolver, $resolutionNote) {
            $dispute->update([
                'status' => DisputeStatus::RESOLVED->value,
                'resolved_by' => $resolver->id,
                'resolved_at' => now(),
                'resolution_note' => $resolutionNote,
            ]);

            $dispute->employeeTimesheet->update([
                'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
            ]);
        });

        $this->auditLogService->log(
            action: 'timesheet.dispute_resolved',
            entityType: TimesheetDispute::class,
            entityId: $dispute->id,
            description: 'Contestação resolvida',
            newValues: ['resolution_note' => $resolutionNote],
            companyId: $dispute->company_id,
        );

        return $dispute->fresh();
    }
}
