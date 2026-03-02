<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\Holiday;
use App\Models\LeavePolicy;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserShift;
use App\Support\CompanyTime;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Vacation accrual (devengo) engine.
 *
 * Calculates accrued days based on computable time.
 */
class VacationAccrualService
{
    public function calculate(User $user, LeavePolicy $policy, Carbon $periodStart, Carbon $periodEnd): array
    {
        $periodStart = $periodStart->copy()->startOfDay();
        $periodEnd = $periodEnd->copy()->startOfDay();

        if ($periodStart->gt($periodEnd)) {
            return $this->emptyResult($policy, $periodStart, $periodEnd);
        }

        $annualEntitlement = $this->resolveAnnualEntitlement($policy);
        $accrualBasis = $this->resolveAccrualBasis($policy);
        $dayWorkThresholdMinutes = $this->resolveDayWorkThresholdMinutes($policy);
        $timezone = CompanyTime::resolveTimezone($user->company);

        $absencesByDate = $this->buildAbsenceMap($user, $periodStart, $periodEnd);
        $workedDates = $this->buildWorkedDateSet($user, $periodStart, $periodEnd, $timezone, $dayWorkThresholdMinutes);

        $period = CarbonPeriod::create($periodStart, $periodEnd);
        $scheduledDates = $accrualBasis === 'scheduled_workdays'
            ? $this->buildScheduledDateSet($user, $periodStart, $periodEnd, $timezone)
            : null;

        $totalDays = 0;
        $computableDays = 0;
        $nonComputableDays = 0;
        $breakdown = [
            'computable_by_basis' => 0,
            'computable_by_work' => 0,
            'computable_by_absence' => 0,
            'non_accrual_absence' => 0,
            'non_activity' => 0,
        ];

        foreach ($period as $date) {
            $dateKey = $date->toDateString();
            $totalDays++;

            $absenceFlag = $absencesByDate[$dateKey] ?? null;
            if ($absenceFlag === false) {
                $nonComputableDays++;
                $breakdown['non_accrual_absence']++;
                continue;
            }

            $inBase = $accrualBasis === 'calendar_days'
                ? true
                : (bool) ($scheduledDates[$dateKey] ?? false);

            if ($inBase) {
                $computableDays++;
                $breakdown['computable_by_basis']++;
                continue;
            }

            if (isset($workedDates[$dateKey])) {
                $computableDays++;
                $breakdown['computable_by_work']++;
                continue;
            }

            if ($absenceFlag === true) {
                $computableDays++;
                $breakdown['computable_by_absence']++;
                continue;
            }

            $nonComputableDays++;
            $breakdown['non_activity']++;
        }

        $accrualRate = $this->calculateAccrualRate($user, $accrualBasis, $periodStart, $timezone, $annualEntitlement);
        $accruedDays = round($computableDays * $accrualRate, 2);

        return [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'annual_entitlement_days' => $annualEntitlement,
            'accrual_basis' => $accrualBasis,
            'computable_days' => $computableDays,
            'non_computable_days' => $nonComputableDays,
            'accrual_rate' => $accrualRate,
            'accrued_days' => $accruedDays,
            'breakdown' => $breakdown,
        ];
    }

    private function emptyResult(LeavePolicy $policy, Carbon $periodStart, Carbon $periodEnd): array
    {
        return [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'annual_entitlement_days' => $this->resolveAnnualEntitlement($policy),
            'accrual_basis' => $this->resolveAccrualBasis($policy),
            'computable_days' => 0,
            'non_computable_days' => 0,
            'accrual_rate' => 0.0,
            'accrued_days' => 0.0,
            'breakdown' => [
                'computable_by_basis' => 0,
                'computable_by_work' => 0,
                'computable_by_absence' => 0,
                'non_accrual_absence' => 0,
                'non_activity' => 0,
            ],
        ];
    }

    private function resolveAnnualEntitlement(LeavePolicy $policy): float
    {
        if (! empty($policy->annual_entitlement_days)) {
            return (float) $policy->annual_entitlement_days;
        }

        if (! empty($policy->days_per_year)) {
            return (float) $policy->days_per_year;
        }

        return 30.0;
    }

    private function resolveAccrualBasis(LeavePolicy $policy): string
    {
        return $policy->accrual_basis ?: 'calendar_days';
    }

    private function resolveDayWorkThresholdMinutes(LeavePolicy $policy): int
    {
        $threshold = (int) ($policy->day_work_threshold_minutes ?? 1);

        return $threshold > 0 ? $threshold : 1;
    }

    /**
     * @return array<string, bool>
     */
    private function buildAbsenceMap(User $user, Carbon $periodStart, Carbon $periodEnd): array
    {
        $absences = Absence::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('start_date', '<=', $periodEnd->toDateString())
            ->where(function ($query) use ($periodStart) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $periodStart->toDateString());
            })
            ->get();

        $map = [];

        foreach ($absences as $absence) {
            $start = Carbon::parse($absence->start_date)->startOfDay();
            $end = $absence->end_date ? Carbon::parse($absence->end_date)->startOfDay() : $start->copy();

            if ($start->lt($periodStart)) {
                $start = $periodStart->copy();
            }

            if ($end->gt($periodEnd)) {
                $end = $periodEnd->copy();
            }

            foreach (CarbonPeriod::create($start, $end) as $date) {
                $dateKey = $date->toDateString();
                $countsForAccrual = (bool) ($absence->counts_for_accrual ?? true);

                if ($countsForAccrual === false) {
                    $map[$dateKey] = false;
                    continue;
                }

                if (! array_key_exists($dateKey, $map)) {
                    $map[$dateKey] = true;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<string, bool>
     */
    private function buildWorkedDateSet(
        User $user,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $timezone,
        int $thresholdMinutes
    ): array {
        $localStart = CarbonImmutable::parse($periodStart->toDateString(), $timezone)->startOfDay();
        $localEnd = CarbonImmutable::parse($periodEnd->toDateString(), $timezone)->endOfDay();
        $utcStart = $localStart->setTimezone('UTC');
        $utcEnd = $localEnd->setTimezone('UTC');

        $entries = $user->timeEntries()
            ->excludeRejected()
            ->whereBetween('clocked_at', [$utcStart->toDateTimeString(), $utcEnd->toDateTimeString()])
            ->orderBy('clocked_at')
            ->orderBy('created_at')
            ->get();

        $entriesByDate = [];

        foreach ($entries as $entry) {
            $localDate = CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone)->toDateString();

            if ($localDate < $periodStart->toDateString() || $localDate > $periodEnd->toDateString()) {
                continue;
            }

            $entriesByDate[$localDate][] = $entry;
        }

        $workedDates = [];
        $thresholdSeconds = $thresholdMinutes * 60;

        foreach ($entriesByDate as $dateKey => $dateEntries) {
            $workedSeconds = $this->calculateWorkedSecondsForEntries($dateEntries, $timezone);

            if ($workedSeconds >= $thresholdSeconds) {
                $workedDates[$dateKey] = true;
            }
        }

        return $workedDates;
    }

    /**
     * @param  array<int, \App\Models\TimeEntry>  $entries
     */
    private function calculateWorkedSecondsForEntries(array $entries, string $timezone): int
    {
        $pairs = [];
        $pendingIn = null;

        foreach ($entries as $entry) {
            if ($entry->type !== 'in' && $entry->type !== 'out') {
                continue;
            }

            $entryTime = CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone);

            if ($entry->type === 'in') {
                if ($pendingIn) {
                    $pairs[] = $this->createPair($pendingIn, $entryTime);
                }

                $pendingIn = $entryTime;
                continue;
            }

            if ($entry->type === 'out' && $pendingIn) {
                $pairs[] = $this->createPair($pendingIn, $entryTime);
                $pendingIn = null;
            }
        }

        return (int) array_sum(array_column($pairs, 'seconds'));
    }

    private function createPair(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $seconds = $end->greaterThan($start) ? $end->diffInSeconds($start, true) : 0;

        return [
            'seconds' => $seconds,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function buildScheduledDateSet(
        User $user,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $timezone
    ): array {
        $scheduleContext = $this->buildScheduleContext($user, $periodStart, $periodEnd);
        $period = CarbonPeriod::create($periodStart, $periodEnd);
        $scheduledDates = [];

        foreach ($period as $date) {
            $localDate = CarbonImmutable::parse($date->toDateString(), $timezone);
            if ($this->isScheduledWorkday($localDate, $scheduleContext)) {
                $scheduledDates[$localDate->toDateString()] = true;
            }
        }

        return $scheduledDates;
    }

    private function calculateAccrualRate(
        User $user,
        string $accrualBasis,
        Carbon $periodStart,
        string $timezone,
        float $annualEntitlement
    ): float {
        $year = (int) $periodStart->year;

        if ($accrualBasis === 'scheduled_workdays') {
            $scheduledWorkdays = $this->countScheduledWorkdaysForYear($user, $year, $timezone);

            if ($scheduledWorkdays <= 0) {
                return 0.0;
            }

            return round($annualEntitlement / $scheduledWorkdays, 6);
        }

        $daysInYear = Carbon::create($year, 1, 1)->daysInYear;

        return round($annualEntitlement / $daysInYear, 6);
    }

    private function countScheduledWorkdaysForYear(User $user, int $year, string $timezone): int
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->startOfDay();
        $scheduleContext = $this->buildScheduleContext($user, $yearStart, $yearEnd);

        $count = 0;
        foreach (CarbonPeriod::create($yearStart, $yearEnd) as $date) {
            $localDate = CarbonImmutable::parse($date->toDateString(), $timezone);
            if ($this->isScheduledWorkday($localDate, $scheduleContext)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{assignments: Collection<int, UserShift>, holidays: array<string, bool>}
     */
    private function buildScheduleContext(User $user, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $assignments = $user->userShifts()
            ->with('shift.shiftDays')
            ->whereDate('start_date', '<=', $rangeEnd->toDateString())
            ->where(function ($query) use ($rangeStart) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $rangeStart->toDateString());
            })
            ->orderByDesc('start_date')
            ->get();

        $holidays = Holiday::query()
            ->where('company_id', $user->company_id)
            ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip()
            ->toArray();

        return [
            'assignments' => $assignments,
            'holidays' => $holidays,
        ];
    }

    private function isScheduledWorkday(CarbonImmutable $date, array $context): bool
    {
        if (isset($context['holidays'][$date->toDateString()])) {
            return false;
        }

        /** @var Collection<int, UserShift> $assignments */
        $assignments = $context['assignments'];
        $shift = $this->resolveShiftForDate($assignments, $date);
        $workingWeekdays = $this->resolveWorkingWeekdays($shift);

        return in_array((int) $date->isoWeekday(), $workingWeekdays, true);
    }

    private function resolveShiftForDate(Collection $assignments, CarbonImmutable $date): ?Shift
    {
        foreach ($assignments as $assignment) {
            $start = Carbon::parse($assignment->start_date)->startOfDay();
            $end = $assignment->end_date ? Carbon::parse($assignment->end_date)->startOfDay() : null;

            if ($date->lessThan($start)) {
                continue;
            }

            if ($end && $date->greaterThan($end)) {
                continue;
            }

            return $assignment->shift;
        }

        return null;
    }

    /**
     * @return array<int, int>
     */
    private function resolveWorkingWeekdays(?Shift $shift): array
    {
        $weekdays = $shift?->shiftDays?->where('is_working_day', true)->pluck('weekday')->map(fn ($w) => (int) $w)->unique()->values()->all();

        if (empty($weekdays)) {
            return [1, 2, 3, 4, 5];
        }

        return $weekdays;
    }
}
