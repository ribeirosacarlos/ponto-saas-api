<?php

namespace App\Services\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetStatus;
use App\Jobs\GenerateEmployeeTimesheetJob;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\TimesheetDispute;
use App\Models\TimesheetSignature;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MonthlyClosureService
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function close(User $admin, User $employee, int $year, int $month): MonthlyClosure
    {
        if ((string) $employee->company_id !== (string) $admin->company_id) {
            abort(404);
        }

        $now = Carbon::now();

        if ($year > $now->year || ($year === $now->year && $month >= $now->month)) {
            throw ValidationException::withMessages([
                'reference_month' => 'Não é possível fechar um mês futuro ou o mês atual.',
            ]);
        }

        $exists = MonthlyClosure::where('company_id', $admin->company_id)
            ->where(function ($query) use ($employee) {
                $query->where('employee_id', $employee->id)
                    ->orWhereNull('employee_id');
            })
            ->where('reference_year', $year)
            ->where('reference_month', $month)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'reference_month' => 'Este mês já foi fechado para este funcionário.',
            ]);
        }

        $closure = DB::transaction(function () use ($admin, $employee, $year, $month) {
            $closure = MonthlyClosure::create([
                'company_id' => $admin->company_id,
                'closed_by' => $admin->id,
                'employee_id' => $employee->id,
                'reference_year' => $year,
                'reference_month' => $month,
                'status' => ClosureStatus::PROCESSING->value,
                'closed_at' => now(),
            ]);

            $timesheet = EmployeeTimesheet::create([
                'company_id' => $admin->company_id,
                'monthly_closure_id' => $closure->id,
                'employee_id' => $employee->id,
                'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
            ]);

            GenerateEmployeeTimesheetJob::dispatch($timesheet);

            return $closure;
        });

        $this->auditLogService->log(
            action: 'monthly_closure.created',
            entityType: MonthlyClosure::class,
            entityId: $closure->id,
            description: "Fechamento mensal criado para {$year}/{$month}",
            newValues: ['year' => $year, 'month' => $month, 'employee_id' => $employee->id, 'status' => ClosureStatus::PROCESSING->value],
            companyId: $admin->company_id,
        );

        return $closure;
    }

    public function delete(User $actor, MonthlyClosure $closure): void
    {
        DB::transaction(function () use ($actor, $closure) {
            $closure = MonthlyClosure::query()
                ->where('company_id', $actor->company_id)
                ->lockForUpdate()
                ->findOrFail($closure->id);

            $timesheets = $closure->timesheets()
                ->withoutGlobalScope('company')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Cascade deletion must never remove a row belonging to another company.
            abort_if($timesheets->contains(
                fn ($timesheet) => (string) $timesheet->company_id !== (string) $actor->company_id
            ), 403);

            abort_if(TimesheetDispute::withoutGlobalScope('company')
                ->whereIn('employee_timesheet_id', $timesheets->modelKeys())
                ->where('company_id', '!=', $actor->company_id)
                ->exists(), 403);

            Gate::forUser($actor)->authorize('delete', $closure);

            $hasSignatures = TimesheetSignature::withoutGlobalScope('company')
                ->whereIn('employee_timesheet_id', $timesheets->modelKeys())
                ->exists();

            if ($hasSignatures) {
                throw ValidationException::withMessages([
                    'closure' => 'Não é possível excluir um fechamento que já possui assinatura, mesmo que invalidada.',
                ]);
            }

            $oldValues = $closure->only([
                'company_id', 'employee_id', 'reference_year', 'reference_month', 'status', 'closed_by',
            ]);
            $oldValues['status'] = $closure->status->value;

            $closure->delete();

            $this->auditLogService->log(
                action: 'monthly_closure.deleted',
                entityType: MonthlyClosure::class,
                entityId: $closure->id,
                description: 'Fechamento mensal sem assinatura excluído',
                oldValues: $oldValues,
                companyId: $actor->company_id,
                userId: $actor->id,
            );
        });
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
