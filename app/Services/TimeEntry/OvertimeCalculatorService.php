<?php

namespace App\Services\TimeEntry;

use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\User;
use App\Models\UserShift;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OvertimeCalculatorService
{
    private const WORK_ENTRY_TYPES = ['in', 'out'];

    /**
     * @return array{employee_id: string, from: string, to: string, timezone: string, totals: array, days?: array}
     */
    public function calculateForEmployee(User $employee, ?CarbonImmutable $from, CarbonImmutable $to, bool $includeDays): array
    {
        $timezone = $this->resolveTimezone($employee);
        $toLocal = $this->normalizeToLocalEnd($to, $timezone);

        $firstEntryDate = $this->resolveFirstEntryDate($employee, $timezone);

        if ($from) {
            $fromLocal = $this->normalizeToLocalStart($from, $timezone);
        } else {
            $fromLocal = $firstEntryDate ?? $toLocal->startOfDay();
        }

        $entries = $this->fetchEntries($employee, $fromLocal, $toLocal);
        $groupedEntries = $this->groupEntriesByDate($entries, $timezone);
        $shiftAssignments = $this->fetchShiftAssignments($employee);
        $defaultShift = $this->fetchDefaultShift($employee);
        $days = $this->iterateDays($fromLocal, $toLocal);
        $holidays = $this->fetchHolidays($employee->company_id, $fromLocal, $toLocal);

        $totalWorked = 0;
        $totalExpected = 0;
        $totalExtra = 0;
        $totalDebt = 0;
        $dailyDetails = [];

        foreach ($days as $day) {
            $date = $day->toDateString();
            $isHoliday = isset($holidays[$date]);
            $shift = $isHoliday ? null : $this->resolveShiftForDate($day, $shiftAssignments, $defaultShift);
            $shiftDay = $shift ? $this->resolveShiftDayForDate($shift, $day) : null;
            $expectedMinutes = $this->expectedMinutesForShiftDay($shiftDay);
            $entriesForDay = $groupedEntries[$date] ?? [];
            $pairResult = $entriesForDay
                ? $this->pairAndSumMinutes($entriesForDay, $shiftDay)
                : ['worked_minutes' => 0, 'ignored' => false, 'reason' => 'no_entries'];
            $workingMinutes = $pairResult['worked_minutes'] ?? 0;
            $ignored = $pairResult['ignored'] ?? false;
            $reason = $pairResult['reason'] ?? null;

            if ($ignored && $reason === 'invalid_pairs') {
                Log::warning('OvertimeCalculatorService ignoring day due to invalid pair', [
                    'employee_id' => $employee->id,
                    'company_id' => $employee->company_id,
                    'date' => $date,
                ]);
            }

            $dailyBalance = $workingMinutes - $expectedMinutes;

            if (! $ignored) {
                $totalWorked += $workingMinutes;
                $totalExpected += $expectedMinutes;
                if ($dailyBalance > 0) {
                    $totalExtra += $dailyBalance;
                }
                if ($dailyBalance < 0) {
                    $totalDebt += $dailyBalance;
                }
            }

            if ($includeDays) {
                $dailyDetails[] = [
                    'date' => $date,
                    'worked_minutes' => $ignored ? 0 : $workingMinutes,
                    'expected_minutes' => $expectedMinutes,
                    'balance_minutes' => $ignored ? 0 : $dailyBalance,
                    'worked_hhmm' => $this->minutesToHHMM($ignored ? 0 : $workingMinutes),
                    'expected_hhmm' => $this->minutesToHHMM($expectedMinutes),
                    'balance_hhmm' => $this->minutesToSignedHHMM($ignored ? 0 : $dailyBalance),
                    'status' => $this->determineStatus($ignored ? 0 : $dailyBalance),
                    'scheduled_minutes' => $expectedMinutes,
                    'scheduled_hhmm' => $this->minutesToHHMM($expectedMinutes),
                    'actual_worked_minutes' => $ignored ? 0 : ($pairResult['actual_worked_minutes'] ?? 0),
                    'actual_worked_hhmm' => $this->minutesToHHMM($ignored ? 0 : ($pairResult['actual_worked_minutes'] ?? 0)),
                    'actual_break_minutes' => $ignored ? 0 : ($pairResult['actual_break_minutes'] ?? 0),
                    'actual_break_hhmm' => $this->minutesToHHMM($ignored ? 0 : ($pairResult['actual_break_minutes'] ?? 0)),
                    'counted_break_minutes' => $ignored ? 0 : ($pairResult['counted_break_minutes'] ?? 0),
                    'counted_break_hhmm' => $this->minutesToHHMM($ignored ? 0 : ($pairResult['counted_break_minutes'] ?? 0)),
                    'ignored' => $ignored,
                    'reason' => $reason,
                    'is_holiday' => $isHoliday,
                    'holiday_name' => $isHoliday ? $holidays[$date] : null,
                ];
            }
        }

        $balanceMinutes = $totalWorked - $totalExpected;
        $totals = [
            'worked_minutes' => $totalWorked,
            'expected_minutes' => $totalExpected,
            'balance_minutes' => $balanceMinutes,
            'extra_minutes' => $totalExtra,
            'debt_minutes' => $totalDebt,
            'abs_debt_minutes' => abs($totalDebt),
            'worked_hhmm' => $this->minutesToHHMM($totalWorked),
            'expected_hhmm' => $this->minutesToHHMM($totalExpected),
            'balance_hhmm' => $this->minutesToSignedHHMM($balanceMinutes),
            'extra_hhmm' => $this->minutesToHHMM($totalExtra),
            'debt_hhmm' => $this->minutesToHHMM(abs($totalDebt)),
        ];

        return array_filter([
            'employee_id' => $employee->id,
            'from' => $fromLocal->toDateString(),
            'to' => $toLocal->toDateString(),
            'counting_since' => $firstEntryDate?->toDateString(),
            'timezone' => $timezone,
            'totals' => $totals,
            'days' => $includeDays ? $dailyDetails : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * Loads all historical shift assignments for the employee, ordered by start_date.
     *
     * @return Collection<int, UserShift>
     */
    protected function fetchShiftAssignments(User $employee): Collection
    {
        return $employee->userShifts()
            ->with([
                'shift.shiftDays' => fn ($q) => $q->orderBy('weekday'),
                'shift.shiftDays.events' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->orderBy('start_date')
            ->get();
    }

    protected function fetchDefaultShift(User $employee): ?Shift
    {
        if (! $employee->company_id) {
            return null;
        }

        return Shift::where('company_id', $employee->company_id)
            ->where('is_default', true)
            ->with([
                'shiftDays' => fn ($q) => $q->orderBy('weekday'),
                'shiftDays.events' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->first();
    }

    /**
     * Resolves which shift was active for a given date based on historical assignments.
     * Falls back to the company default shift when no assignment covers the date.
     *
     * @param  Collection<int, UserShift>  $assignments
     */
    protected function resolveShiftForDate(CarbonImmutable $date, Collection $assignments, ?Shift $default): ?Shift
    {
        $dateStr = $date->toDateString();

        foreach ($assignments as $assignment) {
            $start = $assignment->start_date?->toDateString();
            $end = $assignment->end_date?->toDateString();

            if ($start && $start > $dateStr) {
                continue;
            }

            if ($end && $end < $dateStr) {
                continue;
            }

            return $assignment->shift;
        }

        return $default;
    }

    /**
     * @return array<string, string> date => name
     */
    protected function fetchHolidays(string $companyId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Holiday::where('company_id', $companyId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->mapWithKeys(fn (Holiday $h) => [$h->date->toDateString() => $h->name])
            ->all();
    }

    /**
     * @return Collection<int, \App\Models\TimeEntry>
     */
    protected function fetchEntries(User $employee, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $fromUtc = $from->setTimezone('UTC');
        $toUtc = $to->setTimezone('UTC');

        return $employee->timeEntries()
            ->whereBetween('clocked_at', [$fromUtc->toDateTimeString(), $toUtc->toDateTimeString()])
            ->orderBy('clocked_at')
            ->get();
    }

    /**
     * @param  Collection<int, \App\Models\TimeEntry>  $entries
     * @return array<string, mixed>
     */
    protected function groupEntriesByDate(Collection $entries, string $timezone): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            if (! in_array($entry->type, self::WORK_ENTRY_TYPES, true)) {
                continue;
            }

            $local = CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone);
            $day = $local->toDateString();

            $grouped[$day][] = $local;
        }

        foreach ($grouped as &$items) {
            usort($items, fn (CarbonImmutable $a, CarbonImmutable $b) => $a->lessThan($b) ? -1 : ($a->greaterThan($b) ? 1 : 0));
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array{worked_minutes?: int, actual_worked_minutes?: int, actual_break_minutes?: int, counted_break_minutes?: int, ignored: bool, reason?: string}
     */
    protected function pairAndSumMinutes(array $entries, ?ShiftDay $definition = null): array
    {
        $count = count($entries);

        if ($count < 2) {
            return ['ignored' => true, 'reason' => 'too_few_entries'];
        }

        if ($count % 2 !== 0) {
            return ['ignored' => true, 'reason' => 'open_day_odd_entries'];
        }

        $workedMinutes = 0;
        $breakMinutes = 0;

        for ($i = 0; $i < $count; $i += 2) {
            $in = $entries[$i];
            $out = $entries[$i + 1];

            if ($out->lessThanOrEqualTo($in)) {
                return ['ignored' => true, 'reason' => 'invalid_pairs'];
            }

            $workedMinutes += (int) $out->diffInMinutes($in, true);

            if ($i + 2 < $count) {
                $nextIn = $entries[$i + 2];

                if ($nextIn->lessThan($out)) {
                    return ['ignored' => true, 'reason' => 'invalid_pairs'];
                }

                $breakMinutes += (int) $nextIn->diffInMinutes($out, true);
            }
        }

        $allowedBreakMinutes = $definition ? $this->resolveBreakMinutes($definition) : 0;
        $countedBreakMinutes = (int) min($breakMinutes, $allowedBreakMinutes);

        return [
            'ignored' => false,
            'worked_minutes' => $workedMinutes + $countedBreakMinutes,
            'actual_worked_minutes' => $workedMinutes,
            'actual_break_minutes' => $breakMinutes,
            'counted_break_minutes' => $countedBreakMinutes,
        ];
    }

    protected function resolveTimezone(User $employee): string
    {
        $company = $employee->company ?? $employee->loadMissing('company')->company;

        return $company?->timezone ?? config('app.timezone', 'UTC');
    }

    protected function normalizeToLocalStart(CarbonImmutable $date, string $timezone): CarbonImmutable
    {
        $local = CarbonImmutable::instance($date);

        if ($local->getTimezone()->getName() !== $timezone) {
            $local = $local->setTimezone($timezone);
        }

        return $local->startOfDay();
    }

    protected function normalizeToLocalEnd(CarbonImmutable $date, string $timezone): CarbonImmutable
    {
        $local = CarbonImmutable::instance($date);

        if ($local->getTimezone()->getName() !== $timezone) {
            $local = $local->setTimezone($timezone);
        }

        return $local->endOfDay();
    }

    protected function resolveFirstEntryDate(User $employee, string $timezone): ?CarbonImmutable
    {
        $first = $employee->timeEntries()
            ->whereIn('type', self::WORK_ENTRY_TYPES)
            ->orderBy('clocked_at')
            ->value('clocked_at');

        if (! $first) {
            return null;
        }

        return CarbonImmutable::parse($first)->setTimezone($timezone)->startOfDay();
    }

    protected function resolveShiftDayForDate(?Shift $shift, CarbonImmutable $date): ?ShiftDay
    {
        if (! $shift) {
            return null;
        }

        $weekday = $date->isoWeekday();
        $definition = $shift->shiftDays->firstWhere('weekday', $weekday);

        if (! $definition || ! $definition->is_working_day) {
            return null;
        }

        return $definition;
    }

    protected function expectedMinutesForShiftDay(?ShiftDay $definition): int
    {
        if (! $definition) {
            return 0;
        }

        if ($definition->scheduled_minutes !== null) {
            return max(0, (int) $definition->scheduled_minutes);
        }

        $start = $this->parseShiftTime($definition->start_time);
        $end = $this->parseShiftTime($definition->end_time);

        if (! $start || ! $end) {
            return 0;
        }

        return max(0, $end->diffInMinutes($start, true));
    }

    protected function resolveBreakMinutes(ShiftDay $definition): int
    {
        $declared = (int) ($definition->break_minutes ?? 0);

        if ($declared > 0) {
            return $declared;
        }

        if ($definition->break_start_time && $definition->break_end_time) {
            $breakStart = $this->parseShiftTime($definition->break_start_time);
            $breakEnd = $this->parseShiftTime($definition->break_end_time);

            if ($breakStart && $breakEnd) {
                return max(0, $breakEnd->diffInMinutes($breakStart, true));
            }
        }

        return 0;
    }

    protected function parseShiftTime(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        foreach (['H:i:s', 'H:i'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $value);
            } catch (InvalidFormatException) {
                continue;
            }

            if ($parsed !== false) {
                return $parsed;
            }
        }

        return null;
    }

    protected function minutesToHHMM(int $minutes): string
    {
        $abs = abs($minutes);
        $hours = (int) floor($abs / 60);
        $remaining = $abs % 60;

        return sprintf('%02d:%02d', $hours, $remaining);
    }

    protected function minutesToSignedHHMM(int $minutes): string
    {
        if ($minutes === 0) {
            return '00:00';
        }

        $sign = $minutes > 0 ? '+' : '-';
        $abs = abs($minutes);
        $hours = (int) floor($abs / 60);
        $remaining = $abs % 60;

        return sprintf('%s%02d:%02d', $sign, $hours, $remaining);
    }

    protected function determineStatus(int $balanceMinutes): string
    {
        if ($balanceMinutes > 0) {
            return 'extra';
        }

        if ($balanceMinutes < 0) {
            return 'debt';
        }

        return 'even';
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    protected function iterateDays(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $start = $from->startOfDay();
        $end = $to->startOfDay();
        $days = [];

        while ($start->lessThanOrEqualTo($end)) {
            $days[] = $start;
            $start = $start->addDay();
        }

        return $days;
    }
}
