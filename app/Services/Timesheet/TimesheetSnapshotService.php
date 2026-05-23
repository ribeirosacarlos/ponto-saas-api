<?php

namespace App\Services\Timesheet;

use App\Models\EmployeeTimesheet;
use App\Services\TimeEntry\TimesheetCalculationService;
use Carbon\CarbonImmutable;

class TimesheetSnapshotService
{
    public function __construct(
        protected TimesheetCalculationService $calculationService,
        protected TimesheetSignatureService $signatureService
    ) {}

    public function generate(EmployeeTimesheet $timesheet): void
    {
        $closure = $timesheet->monthlyClosure;
        $employee = $timesheet->employee;

        $timezone = $employee->company?->timezone ?? config('app.timezone', 'UTC');

        $from = CarbonImmutable::create(
            $closure->reference_year,
            $closure->reference_month,
            1,
            0, 0, 0,
            $timezone
        );

        $to = $from->endOfMonth();

        $snapshot = $this->calculationService->calculateForEmployee($employee, $from, $to);

        $previousBalanceMinutes = $this->calculatePreviousBalance($timesheet);
        $currentBalanceMinutes = (int) ($snapshot['totals']['balance_minutes'] ?? 0);

        $snapshot['previous_balance_minutes'] = $previousBalanceMinutes;
        $snapshot['previous_balance_hhmm'] = $this->minutesToSignedHHMM($previousBalanceMinutes);
        $snapshot['accumulated_balance_minutes'] = $previousBalanceMinutes + $currentBalanceMinutes;
        $snapshot['accumulated_balance_hhmm'] = $this->minutesToSignedHHMM($previousBalanceMinutes + $currentBalanceMinutes);

        // Antes de salvar o novo snapshot, verifica se havia assinaturas ativas.
        // Se houver, invalida-as (superseded) pois o documento foi alterado.
        $this->signatureService->supersedePreviousSignatures($timesheet);

        $timesheet->update([
            'snapshot' => $snapshot,
            'snapshot_generated_at' => now(),
        ]);
    }

    private function calculatePreviousBalance(EmployeeTimesheet $timesheet): int
    {
        $closure = $timesheet->monthlyClosure;

        $previousTimesheets = EmployeeTimesheet::query()
            ->where('employee_id', $timesheet->employee_id)
            ->where('company_id', $timesheet->company_id)
            ->where('id', '!=', $timesheet->id)
            ->whereHas('monthlyClosure', function ($query) use ($closure) {
                $query->where(function ($q) use ($closure) {
                    $q->where('reference_year', '<', $closure->reference_year)
                        ->orWhere(function ($q2) use ($closure) {
                            $q2->where('reference_year', $closure->reference_year)
                                ->where('reference_month', '<', $closure->reference_month);
                        });
                });
            })
            ->get(['snapshot']);

        return $previousTimesheets->sum(function (EmployeeTimesheet $ts) {
            return (int) ($ts->snapshot['totals']['balance_minutes'] ?? 0);
        });
    }

    private function minutesToSignedHHMM(int $minutes): string
    {
        if ($minutes === 0) {
            return '00:00';
        }

        $sign = $minutes > 0 ? '+' : '-';
        $abs = abs($minutes);

        return sprintf('%s%02d:%02d', $sign, (int) floor($abs / 60), $abs % 60);
    }
}
