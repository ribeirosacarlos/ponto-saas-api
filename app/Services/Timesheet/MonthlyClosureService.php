<?php

namespace App\Services\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetStatus;
use App\Jobs\GenerateEmployeeTimesheetJob;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonthlyClosureService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function close(User $admin, int $year, int $month): MonthlyClosure
    {
        $now = Carbon::now();

        if ($year > $now->year || ($year === $now->year && $month >= $now->month)) {
            throw ValidationException::withMessages([
                'reference_month' => 'Não é possível fechar um mês futuro ou o mês atual.',
            ]);
        }

        $exists = MonthlyClosure::where('company_id', $admin->company_id)
            ->where('reference_year', $year)
            ->where('reference_month', $month)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'reference_month' => 'Este mês já foi fechado para esta empresa.',
            ]);
        }

        $closure = DB::transaction(function () use ($admin, $year, $month) {
            $closure = MonthlyClosure::create([
                'company_id' => $admin->company_id,
                'closed_by' => $admin->id,
                'reference_year' => $year,
                'reference_month' => $month,
                'status' => ClosureStatus::PROCESSING->value,
                'closed_at' => now(),
            ]);

            $employeeRoleId = Role::where('name', 'employee')->value('id');

            $employees = User::where('company_id', $admin->company_id)
                ->whereHas('roles', fn ($q) => $q->where('roles.id', $employeeRoleId))
                ->get();

            foreach ($employees as $employee) {
                $timesheet = EmployeeTimesheet::create([
                    'company_id' => $admin->company_id,
                    'monthly_closure_id' => $closure->id,
                    'employee_id' => $employee->id,
                    'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
                ]);

                GenerateEmployeeTimesheetJob::dispatch($timesheet);
            }

            return $closure;
        });

        $this->auditLogService->log(
            action: 'monthly_closure.created',
            entityType: MonthlyClosure::class,
            entityId: $closure->id,
            description: "Fechamento mensal criado para {$year}/{$month}",
            newValues: ['year' => $year, 'month' => $month, 'status' => ClosureStatus::PROCESSING->value],
            companyId: $admin->company_id,
        );

        return $closure;
    }

    public function checkAndAdvanceToOpen(MonthlyClosure $closure): void
    {
        DB::transaction(function () use ($closure) {
            $closure = MonthlyClosure::lockForUpdate()->find($closure->id);

            if (! $closure || $closure->status !== ClosureStatus::PROCESSING) {
                return;
            }

            $pending = $closure->timesheets()->whereNull('snapshot_generated_at')->count();

            if ($pending === 0) {
                $closure->update(['status' => ClosureStatus::OPEN->value]);
            }
        });
    }

    public function checkAndAdvanceToClosed(MonthlyClosure $closure): void
    {
        DB::transaction(function () use ($closure) {
            $closure = MonthlyClosure::lockForUpdate()->find($closure->id);

            if (! $closure || $closure->status !== ClosureStatus::OPEN) {
                return;
            }

            $incomplete = $closure->timesheets()
                ->where('status', '!=', TimesheetStatus::COMPLETED->value)
                ->count();

            if ($incomplete === 0) {
                $closure->update(['status' => ClosureStatus::COMPLETED->value]);
            }
        });
    }
}
