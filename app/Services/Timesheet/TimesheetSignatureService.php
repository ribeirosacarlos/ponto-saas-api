<?php

namespace App\Services\Timesheet;

use App\Enums\TimesheetSignatureRole;
use App\Enums\TimesheetStatus;
use App\Models\EmployeeTimesheet;
use App\Models\TimesheetSignature;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\UserVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TimesheetSignatureService
{
    public function __construct(
        protected UserVisibilityService $userVisibilityService,
        protected MonthlyClosureService $closureService,
        protected AuditLogService $auditLogService
    ) {}

    public function signAsEmployee(EmployeeTimesheet $timesheet, User $signer, Request $request): TimesheetSignature
    {
        if ((string) $signer->id !== (string) $timesheet->employee_id) {
            abort(403, 'Você só pode assinar a própria folha.');
        }

        if ($timesheet->status !== TimesheetStatus::PENDING_EMPLOYEE) {
            throw ValidationException::withMessages([
                'status' => 'A folha não está aguardando assinatura do colaborador.',
            ]);
        }

        if ($timesheet->hasOpenDispute()) {
            throw ValidationException::withMessages([
                'dispute' => 'Existe uma contestação em aberto. Resolva antes de assinar.',
            ]);
        }

        $signature = DB::transaction(function () use ($timesheet, $signer, $request) {
            $signature = TimesheetSignature::create([
                'company_id' => $timesheet->company_id,
                'employee_timesheet_id' => $timesheet->id,
                'signer_id' => $signer->id,
                'role' => TimesheetSignatureRole::EMPLOYEE->value,
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $timesheet->update(['status' => TimesheetStatus::PENDING_MANAGER->value]);

            return $signature;
        });

        $this->auditLogService->log(
            action: 'timesheet.signed_by_employee',
            entityType: EmployeeTimesheet::class,
            entityId: $timesheet->id,
            description: 'Folha assinada pelo colaborador',
            companyId: $timesheet->company_id,
        );

        return $signature;
    }

    public function signAsManager(EmployeeTimesheet $timesheet, User $signer, Request $request): TimesheetSignature
    {
        $employee = $timesheet->employee;

        if (! $this->userVisibilityService->canManageUser($signer, $employee)) {
            abort(403, 'Você não tem permissão para assinar a folha deste colaborador.');
        }

        if ($timesheet->status !== TimesheetStatus::PENDING_MANAGER) {
            throw ValidationException::withMessages([
                'status' => 'A folha não está aguardando assinatura do gestor.',
            ]);
        }

        $signature = DB::transaction(function () use ($timesheet, $signer, $request) {
            $signature = TimesheetSignature::create([
                'company_id' => $timesheet->company_id,
                'employee_timesheet_id' => $timesheet->id,
                'signer_id' => $signer->id,
                'role' => TimesheetSignatureRole::MANAGER->value,
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $timesheet->update(['status' => TimesheetStatus::COMPLETED->value]);

            $this->closureService->checkAndAdvanceToClosed($timesheet->monthlyClosure);

            return $signature;
        });

        $this->auditLogService->log(
            action: 'timesheet.signed_by_manager',
            entityType: EmployeeTimesheet::class,
            entityId: $timesheet->id,
            description: 'Folha assinada pelo gestor',
            companyId: $timesheet->company_id,
        );

        return $signature;
    }
}
