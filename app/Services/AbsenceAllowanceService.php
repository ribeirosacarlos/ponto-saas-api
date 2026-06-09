<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\MonthlyClosure;
use App\Models\User;
use App\Models\VacationDay;
use App\Services\TimeEntry\AbsenceTimeEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AbsenceAllowanceService
{
    public function __construct(
        protected AbsenceTimeEntryService $absenceTimeEntryService
    ) {}

    public function createFromAdmin(User $actor, User $employee, array $payload): Absence
    {
        [$startDate, $endDate, $startTime, $endTime] = $this->normalizeCoverage($payload);

        $this->assertNoClosedMonthlyClosure($employee->company_id, $startDate, $endDate);
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

            return $absence->fresh();
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

    private function assertNoClosedMonthlyClosure(string $companyId, string $startDate, string $endDate): void
    {
        foreach ($this->yearMonthPairs($startDate, $endDate) as [$year, $month]) {
            $exists = MonthlyClosure::query()
                ->where('company_id', $companyId)
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
