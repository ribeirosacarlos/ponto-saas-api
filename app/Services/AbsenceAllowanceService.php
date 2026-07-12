<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\MonthlyClosure;
use App\Models\User;
use App\Models\VacationDay;
use App\Services\TimeEntry\AbsenceTimeEntryService;
use App\Services\TimeEntry\TimesheetCalculationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AbsenceAllowanceService
{
    public function __construct(
        protected AbsenceTimeEntryService $absenceTimeEntryService,
        protected TimesheetCalculationService $timesheetCalculationService
    ) {}

    /**
     * @return array{0: Absence, 1: array<int, array{date: string, missing_minutes: int, missing_hhmm: string, message: string}>}
     */
    public function createFromAdmin(User $actor, User $employee, array $payload): array
    {
        [$startDate, $endDate, $startTime, $endTime] = $this->normalizeCoverage($payload);

        $this->assertNoClosedMonthlyClosure($employee->company_id, $employee->id, $startDate, $endDate);
        $this->assertNoOverlappingAbsence($employee->company_id, $employee->id, $startDate, $endDate);
        $this->assertNoOverlappingVacation($employee->company_id, $employee->id, $startDate, $endDate);

        $status = $payload['status'] ?? Absence::STATUS_RECORDED;

        return DB::transaction(function () use ($actor, $employee, $payload, $startDate, $endDate, $startTime, $endTime, $status) {
            $absence = Absence::create([
                'company_id' => $employee->company_id,
                'user_id' => $employee->id,
                'type' => $payload['type'] ?? Absence::TYPE_EXCUSED_ABSENCE,
                'coverage_type' => $payload['coverage_type'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $status,
                'comment' => $payload['comment'] ?? null,
                'counts_for_accrual' => $payload['counts_for_accrual'] ?? true,
                'created_by' => $actor->id,
                'approved_by' => $status === Absence::STATUS_APPROVED ? $actor->id : null,
                'approved_at' => $status === Absence::STATUS_APPROVED ? now() : null,
            ]);

            $this->absenceTimeEntryService->syncForAbsence($absence);

            return [$absence->fresh(), $this->buildIncompleteCoverageWarnings($absence, $employee)];
        });
    }

    /**
     * @return array<int, array{date: string, missing_minutes: int, missing_hhmm: string, message: string}>
     */
    private function buildIncompleteCoverageWarnings(Absence $absence, User $employee): array
    {
        if (! $absence->isHoursCoverage()) {
            return [];
        }

        $from = CarbonImmutable::parse($absence->start_date);
        $to = CarbonImmutable::parse($absence->end_date ?? $absence->start_date);
        $result = $this->timesheetCalculationService->calculateForEmployee($employee, $from, $to);

        $warnings = [];
        foreach ($result['days'] as $day) {
            $debt = (int) ($day['summary']['debt_minutes'] ?? 0);

            if ($debt < 0) {
                $missing = abs($debt);
                $warnings[] = [
                    'date' => $day['date'],
                    'missing_minutes' => $missing,
                    'missing_hhmm' => sprintf('%02d:%02d', intdiv($missing, 60), $missing % 60),
                    'message' => sprintf(
                        'Abono nao cobre a jornada esperada do dia %s. Faltam %02d:%02d para completar a carga horaria.',
                        $day['date'],
                        intdiv($missing, 60),
                        $missing % 60
                    ),
                ];
            }
        }

        return $warnings;
    }

    public function destroyFromAdmin(Absence $absence): void
    {
        $startDate = $absence->start_date->toDateString();
        $endDate = $absence->end_date?->toDateString() ?? $startDate;

        $this->assertNoClosedMonthlyClosure($absence->company_id, $absence->user_id, $startDate, $endDate);

        DB::transaction(function () use ($absence) {
            $this->absenceTimeEntryService->deleteGeneratedEntriesForAbsence($absence);
            $absence->delete();
        });
    }

    private function normalizeCoverage(array $payload): array
    {
        if ($payload['coverage_type'] === Absence::COVERAGE_HOURS) {
            return [
                $payload['date'],
                $payload['date'],
                $payload['start_time'],
                $payload['end_time'],
            ];
        }

        return [
            $payload['start_date'],
            $payload['end_date'] ?? $payload['start_date'],
            null,
            null,
        ];
    }

    private function assertNoClosedMonthlyClosure(string $companyId, string $userId, string $startDate, string $endDate): void
    {
        foreach ($this->yearMonthPairs($startDate, $endDate) as [$year, $month]) {
            $exists = MonthlyClosure::query()
                ->where('company_id', $companyId)
                ->where(function ($query) use ($userId) {
                    $query->where('employee_id', $userId)
                        ->orWhereNull('employee_id');
                })
                ->where('reference_year', $year)
                ->where('reference_month', $month)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'start_date' => 'Nao e possivel lancar abono em mes com fechamento existente.',
                ]);
            }
        }
    }

    private function assertNoOverlappingAbsence(string $companyId, string $userId, string $startDate, string $endDate): void
    {
        $exists = Absence::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereIn('status', Absence::BLOCKING_STATUSES)
            ->whereDate('start_date', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $startDate);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'start_date' => 'Ja existe ausencia ou abono no periodo informado.',
            ]);
        }
    }

    private function assertNoOverlappingVacation(string $companyId, string $userId, string $startDate, string $endDate): void
    {
        $exists = VacationDay::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'start_date' => 'Ja existe ferias no periodo informado.',
            ]);
        }
    }

    private function yearMonthPairs(string $startDate, string $endDate): array
    {
        $cursor = CarbonImmutable::parse($startDate)->startOfMonth();
        $end = CarbonImmutable::parse($endDate)->startOfMonth();
        $pairs = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $pairs[] = [$cursor->year, $cursor->month];
            $cursor = $cursor->addMonth();
        }

        return $pairs;
    }
}
