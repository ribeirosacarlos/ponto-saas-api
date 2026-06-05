<?php

namespace App\Services\TimeEntry;

use App\Models\Absence;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use App\Models\VacationDay;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection;

class TimesheetCalculationService
{
    private const WORK_ENTRY_TYPES = ['in', 'out'];

    /**
     * @return array{
     *   employee_id: string,
     *   from: string,
     *   to: string,
     *   counting_since?: string,
     *   timezone: string,
     *   totals: array<string, int|string>,
     *   days: array<int, array{
     *     date: string,
     *     employee_id: string,
     *     entries: array<int, array<string, mixed>>,
     *     summary: array<string, mixed>
     *   }>
     * }
     */
    public function calculateForEmployee(
        User $employee,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
        bool $onlyDaysWithEntries = false
    ): array {
        $timezone = $this->resolveTimezone($employee);
        $toLocal = $this->normalizeToLocalEnd($to ?? CarbonImmutable::now($timezone), $timezone);
        $firstEntryDate = $this->resolveFirstEntryDate($employee, $timezone);

        if ($from) {
            $fromLocal = $this->normalizeToLocalStart($from, $timezone);
        } else {
            $fromLocal = $firstEntryDate ?? $toLocal->startOfDay();
        }

        if ($fromLocal->greaterThan($toLocal)) {
            [$fromLocal, $toLocal] = [$toLocal->startOfDay(), $fromLocal->endOfDay()];
        }

        $entries = $this->fetchEntries($employee, $fromLocal, $toLocal);
        $entriesByDate = $this->groupEntriesByDate($entries, $timezone);
        $shiftAssignments = $this->fetchShiftAssignments($employee);
        $defaultShift = $this->fetchDefaultShift($employee);
        $holidays = $this->fetchHolidays($employee->company_id, $fromLocal, $toLocal);
        $vacationDays = $this->fetchVacationDays($employee, $fromLocal, $toLocal);
        $absences = $this->fetchAbsences($employee, $fromLocal, $toLocal);

        $days = [];
        foreach ($this->iterateDays($fromLocal, $toLocal) as $day) {
            $date = $day->toDateString();
            $dayEntries = $entriesByDate[$date] ?? [];

            if ($onlyDaysWithEntries && $dayEntries === []) {
                continue;
            }

            $days[] = $this->buildDaySummary(
                $employee,
                $day,
                $dayEntries,
                $holidays,
                $vacationDays,
                $absences,
                $shiftAssignments,
                $defaultShift,
                $timezone
            );
        }

        return array_filter([
            'employee_id' => $employee->id,
            'from' => $fromLocal->toDateString(),
            'to' => $toLocal->toDateString(),
            'counting_since' => $firstEntryDate ? $firstEntryDate->toDateString() : null,
            'timezone' => $timezone,
            'totals' => $this->buildTotals($days),
            'days' => $days,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @return array<string, array<int, TimeEntry>>
     */
    protected function groupEntriesByDate(Collection $entries, string $timezone): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            $local = CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone);
            $grouped[$local->toDateString()][] = $entry;
        }

        foreach ($grouped as &$items) {
            usort($items, function (TimeEntry $left, TimeEntry $right) {
                return $left->clocked_at->getTimestamp() <=> $right->clocked_at->getTimestamp();
            });
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return Collection<int, UserShift>
     */
    protected function fetchShiftAssignments(User $employee): Collection
    {
        return $employee->userShifts()
            ->with([
                'shift.shiftDays' => fn ($query) => $query->orderBy('weekday'),
                'shift.shiftDays.events' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->orderBy('start_date')
            ->get();
    }

    protected function fetchDefaultShift(User $employee): ?Shift
    {
        if (! $employee->company_id) {
            return null;
        }

        return Shift::query()
            ->where('company_id', $employee->company_id)
            ->where('is_default', true)
            ->with([
                'shiftDays' => fn ($query) => $query->orderBy('weekday'),
                'shiftDays.events' => fn ($query) => $query->orderBy('sort_order'),
            ])
            ->first();
    }

    /**
     * @return Collection<int, TimeEntry>
     */
    protected function fetchEntries(User $employee, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $fromUtc = $from->setTimezone('UTC');
        $toUtc = $to->setTimezone('UTC');

        return $employee->timeEntries()
            ->whereBetween('clocked_at', [$fromUtc->toDateTimeString(), $toUtc->toDateTimeString()])
            ->orderBy('clocked_at')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array<string, string>
     */
    protected function fetchHolidays(?string $companyId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if (! $companyId) {
            return [];
        }

        return Holiday::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->mapWithKeys(fn (Holiday $holiday) => [$holiday->date->toDateString() => $holiday->name])
            ->all();
    }

    /**
     * @return array<string, VacationDay>
     */
    protected function fetchVacationDays(User $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return VacationDay::query()
            ->where('company_id', $employee->company_id)
            ->where('user_id', $employee->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (VacationDay $day) => $day->date->toDateString())
            ->all();
    }

    /**
     * @return array<string, Absence>
     */
    protected function fetchAbsences(User $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $effectiveStatuses = ['approved', 'recorded'];
        $absences = Absence::query()
            ->where('company_id', $employee->company_id)
            ->where('user_id', $employee->id)
            ->whereIn('status', $effectiveStatuses)
            ->whereDate('start_date', '<=', $to->toDateString())
            ->where(function ($query) use ($from) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $from->toDateString());
            })
            ->orderBy('start_date')
            ->get();

        $mapped = [];
        foreach ($absences as $absence) {
            $cursor = CarbonImmutable::parse($absence->start_date)->startOfDay();
            $end = CarbonImmutable::parse($absence->end_date ?? $absence->start_date)->startOfDay();

            while ($cursor->lessThanOrEqualTo($end)) {
                $date = $cursor->toDateString();
                if ($date >= $from->toDateString() && $date <= $to->toDateString() && ! isset($mapped[$date])) {
                    $mapped[$date] = $absence;
                }

                $cursor = $cursor->addDay();
            }
        }

        return $mapped;
    }

    protected function buildDaySummary(
        User $employee,
        CarbonImmutable $date,
        array $entries,
        array $holidays,
        array $vacationDays,
        array $absences,
        Collection $shiftAssignments,
        ?Shift $defaultShift,
        string $timezone
    ): array {
        $dateKey = $date->toDateString();
        $isHoliday = isset($holidays[$dateKey]);
        $vacationDay = $vacationDays[$dateKey] ?? null;
        $absence = $absences[$dateKey] ?? null;

        $shift = $this->resolveShiftForDate($date, $shiftAssignments, $defaultShift);
        $shiftDay = $this->resolveShiftDayForDate($shift, $date);
        $isRegularDayOff = $shift !== null && $shiftDay === null;
        $isLeaveDay = $vacationDay !== null || $absence !== null;
        $expectedMinutes = ($isHoliday || $isRegularDayOff || $isLeaveDay)
            ? 0
            : $this->expectedMinutesForShiftDay($shiftDay);
        $allowedBreakMinutes = ($isHoliday || $isRegularDayOff || $isLeaveDay)
            ? 0
            : $this->resolveBreakMinutes($shiftDay);

        $workEntries = array_values(array_filter(
            $entries,
            fn (TimeEntry $entry) => in_array($entry->type, self::WORK_ENTRY_TYPES, true)
                && $entry->adjustment_status !== 'rejected'
        ));
        $pairing = $this->pairWorkEntries($workEntries, $timezone, $allowedBreakMinutes);
        $workedMinutes = $this->resolveOfficialWorkedMinutes(
            $pairing['raw_worked_minutes'],
            $pairing['has_incomplete_entries']
        );
        $isFinalized = $dateKey < CarbonImmutable::now($timezone)->toDateString();
        $balanceMinutes = $isFinalized ? $workedMinutes - $expectedMinutes - $pairing['exceeded_break_minutes'] : 0;
        $extraMinutes = max(0, $balanceMinutes);
        $debtMinutes = min(0, $balanceMinutes);

        return [
            'date' => $dateKey,
            'employee_id' => $employee->id,
            'entries' => array_map(
                fn (TimeEntry $entry) => $this->formatEntry($entry, $timezone),
                $entries
            ),
            'summary' => [
                'worked_minutes' => $workedMinutes,
                'worked_hhmm' => $this->minutesToHHMM($workedMinutes),
                'raw_worked_minutes' => $pairing['raw_worked_minutes'],
                'raw_worked_hhmm' => $this->minutesToHHMM($pairing['raw_worked_minutes']),
                'actual_worked_minutes' => $pairing['raw_worked_minutes'],
                'actual_worked_hhmm' => $this->minutesToHHMM($pairing['raw_worked_minutes']),
                'expected_minutes' => $expectedMinutes,
                'expected_hhmm' => $this->minutesToHHMM($expectedMinutes),
                'real_break_minutes' => $pairing['real_break_minutes'],
                'real_break_hhmm' => $this->minutesToHHMM($pairing['real_break_minutes']),
                'actual_break_minutes' => $pairing['real_break_minutes'],
                'actual_break_hhmm' => $this->minutesToHHMM($pairing['real_break_minutes']),
                'allowed_break_minutes' => $allowedBreakMinutes,
                'allowed_break_hhmm' => $this->minutesToHHMM($allowedBreakMinutes),
                'counted_break_minutes' => $pairing['counted_break_minutes'],
                'counted_break_hhmm' => $this->minutesToHHMM($pairing['counted_break_minutes']),
                'exceeded_break_minutes' => $pairing['exceeded_break_minutes'],
                'exceeded_break_hhmm' => $this->minutesToHHMM($pairing['exceeded_break_minutes']),
                'balance_minutes' => $balanceMinutes,
                'balance_hhmm' => $this->minutesToSignedHHMM($balanceMinutes),
                'extra_minutes' => $extraMinutes,
                'extra_hhmm' => $this->minutesToHHMM($extraMinutes),
                'debt_minutes' => $debtMinutes,
                'debt_hhmm' => $this->minutesToHHMM(abs($debtMinutes)),
                'status' => $this->determineStatus($balanceMinutes),
                'is_finalized' => $isFinalized,
                'is_holiday' => $isHoliday,
                'holiday_name' => $holidays[$dateKey] ?? null,
                'is_day_off' => $isRegularDayOff,
                'is_vacation' => $vacationDay !== null,
                'is_absence' => $absence !== null,
                'absence_type' => $absence ? $absence->type : null,
                'has_incomplete_entries' => $pairing['has_incomplete_entries'],
                'open_session' => $pairing['open_session'],
                'pair_count' => $pairing['pair_count'],
                'pair_details' => $pairing['pairs'],
                'open_pair' => $pairing['open_pair'],
            ],
        ];
    }

    /**
     * @param  Collection<int, UserShift>  $assignments
     */
    protected function resolveShiftForDate(CarbonImmutable $date, Collection $assignments, ?Shift $defaultShift): ?Shift
    {
        $dateString = $date->toDateString();

        foreach ($assignments as $assignment) {
            $start = $assignment->start_date ? $assignment->start_date->toDateString() : null;
            $end = $assignment->end_date ? $assignment->end_date->toDateString() : null;

            if ($start && $start > $dateString) {
                continue;
            }

            if ($end && $end < $dateString) {
                continue;
            }

            return $assignment->shift;
        }

        return $defaultShift;
    }

    protected function resolveShiftDayForDate(?Shift $shift, CarbonImmutable $date): ?ShiftDay
    {
        if (! $shift) {
            return null;
        }

        $definition = $shift->shiftDays->firstWhere('weekday', $date->isoWeekday());

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

    protected function resolveBreakMinutes(?ShiftDay $definition): int
    {
        if (! $definition) {
            return 0;
        }

        $declared = (int) ($definition->break_minutes ?? 0);
        if ($declared > 0) {
            return $declared;
        }

        if (! $definition->break_start_time || ! $definition->break_end_time) {
            return 0;
        }

        $breakStart = $this->parseShiftTime($definition->break_start_time);
        $breakEnd = $this->parseShiftTime($definition->break_end_time);

        if (! $breakStart || ! $breakEnd) {
            return 0;
        }

        return max(0, $breakEnd->diffInMinutes($breakStart, true));
    }

    /**
     * @param  array<int, TimeEntry>  $entries
     * @return array{
     *   raw_worked_minutes: int,
     *   real_break_minutes: int,
     *   counted_break_minutes: int,
     *   exceeded_break_minutes: int,
     *   has_incomplete_entries: bool,
     *   open_session: bool,
     *   pair_count: int,
     *   pairs: array<int, array{in: string, out: string, minutes: int}>,
     *   open_pair: ?array{in: string}
     * }
     */
    protected function pairWorkEntries(array $entries, string $timezone, int $allowedBreakMinutes): array
    {
        $pendingIn = null;
        $previousOut = null;
        $rawWorkedMinutes = 0;
        $realBreakMinutes = 0;
        $hasIncompleteEntries = false;
        $pairs = [];

        foreach ($entries as $entry) {
            $clockedAt = $this->truncateToMinute(
                CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone)
            );

            if ($entry->type === 'in') {
                if ($pendingIn !== null) {
                    $hasIncompleteEntries = true;
                }

                if ($previousOut !== null && $clockedAt->greaterThan($previousOut)) {
                    $realBreakMinutes += (int) $clockedAt->diffInMinutes($previousOut, true);
                    $previousOut = null;
                }

                $pendingIn = $clockedAt;

                continue;
            }

            if ($pendingIn === null) {
                $hasIncompleteEntries = true;

                continue;
            }

            if ($clockedAt->lessThanOrEqualTo($pendingIn)) {
                $hasIncompleteEntries = true;
                $pendingIn = null;
                $previousOut = null;

                continue;
            }

            $minutes = (int) $clockedAt->diffInMinutes($pendingIn, true);
            $rawWorkedMinutes += $minutes;
            $pairs[] = [
                'in' => $pendingIn->toIso8601String(),
                'out' => $clockedAt->toIso8601String(),
                'minutes' => $minutes,
            ];
            $previousOut = $clockedAt;
            $pendingIn = null;
        }

        if ($pendingIn !== null) {
            $hasIncompleteEntries = true;
        }

        $countedBreakMinutes = min($realBreakMinutes, $allowedBreakMinutes);

        return [
            'raw_worked_minutes' => $rawWorkedMinutes,
            'real_break_minutes' => $realBreakMinutes,
            'counted_break_minutes' => $countedBreakMinutes,
            'exceeded_break_minutes' => max(0, $realBreakMinutes - $allowedBreakMinutes),
            'has_incomplete_entries' => $hasIncompleteEntries,
            'open_session' => $pendingIn !== null,
            'pair_count' => count($pairs),
            'pairs' => $pairs,
            'open_pair' => $pendingIn ? ['in' => $pendingIn->toIso8601String()] : null,
        ];
    }

    protected function resolveOfficialWorkedMinutes(
        int $rawWorkedMinutes,
        bool $hasIncompleteEntries
    ): int {
        if ($hasIncompleteEntries || $rawWorkedMinutes <= 0) {
            return 0;
        }

        return $rawWorkedMinutes;
    }

    protected function truncateToMinute(CarbonImmutable $date): CarbonImmutable
    {
        return $date->setTime($date->hour, $date->minute, 0, 0);
    }

    /**
     * @param  array<int, array{summary: array<string, mixed>}>  $days
     * @return array<string, int|string>
     */
    protected function buildTotals(array $days): array
    {
        $worked = 0;
        $expected = 0;
        $balance = 0;
        $extra = 0;
        $debt = 0;
        $daysWorked = 0;
        $absencesCount = 0;

        foreach ($days as $day) {
            $summary = $day['summary'];
            $worked += (int) $summary['worked_minutes'];
            $expected += (int) $summary['expected_minutes'];
            $balance += (int) $summary['balance_minutes'];
            $extra += (int) $summary['extra_minutes'];
            $debt += (int) $summary['debt_minutes'];

            if ((int) $summary['worked_minutes'] > 0) {
                $daysWorked++;
            }

            $isMissed = (int) $summary['expected_minutes'] > 0
                && (int) $summary['worked_minutes'] === 0
                && ! $summary['is_holiday']
                && ! $summary['is_day_off']
                && ! $summary['is_vacation']
                && ! $summary['is_absence'];

            if ($isMissed) {
                $absencesCount++;
            }
        }

        return [
            'worked_minutes' => $worked,
            'expected_minutes' => $expected,
            'balance_minutes' => $balance,
            'extra_minutes' => $extra,
            'debt_minutes' => $debt,
            'abs_debt_minutes' => abs($debt),
            'days_worked' => $daysWorked,
            'absences_count' => $absencesCount,
            'worked_hhmm' => $this->minutesToHHMM($worked),
            'expected_hhmm' => $this->minutesToHHMM($expected),
            'balance_hhmm' => $this->minutesToSignedHHMM($balance),
            'extra_hhmm' => $this->minutesToHHMM($extra),
            'debt_hhmm' => $this->minutesToHHMM(abs($debt)),
        ];
    }

    protected function resolveTimezone(User $employee): string
    {
        $company = $employee->company ?? $employee->loadMissing('company')->company;

        return $company ? $company->timezone : (config('app.timezone', 'UTC'));
    }

    protected function resolveFirstEntryDate(User $employee, string $timezone): ?CarbonImmutable
    {
        $first = $employee->timeEntries()
            ->whereIn('type', self::WORK_ENTRY_TYPES)
            ->excludeRejected()
            ->orderBy('clocked_at')
            ->value('clocked_at');

        if (! $first) {
            return null;
        }

        return CarbonImmutable::parse($first)->setTimezone($timezone)->startOfDay();
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

    protected function parseShiftTime(?string $value): ?CarbonImmutable
    {
        if (! $value) {
            return null;
        }

        foreach (['H:i:s', 'H:i'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $value);
            } catch (InvalidFormatException $exception) {
                continue;
            }

            if ($parsed !== false) {
                return $this->truncateToMinute($parsed);
            }
        }

        return null;
    }

    protected function formatEntry(TimeEntry $entry, string $timezone): array
    {
        return [
            'id' => $entry->id,
            'type' => $entry->type,
            'clocked_at' => $this->formatIso8601ToMinute(
                CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone)
            ),
            'event_kind' => $entry->event_kind,
            'adjustment_status' => $entry->adjustment_status,
            'adjustment_reason' => $entry->adjustment_reason,
            'source' => $entry->source,
        ];
    }

    protected function formatIso8601ToMinute(CarbonImmutable $dateTime): string
    {
        return $this->truncateToMinute($dateTime)->format('Y-m-d\TH:iP');
    }

    protected function minutesToHHMM(int $minutes): string
    {
        $minutes = abs($minutes);

        return sprintf('%02d:%02d', (int) floor($minutes / 60), $minutes % 60);
    }

    protected function minutesToSignedHHMM(int $minutes): string
    {
        if ($minutes === 0) {
            return '00:00';
        }

        $sign = $minutes > 0 ? '+' : '-';
        $abs = abs($minutes);

        return sprintf('%s%02d:%02d', $sign, (int) floor($abs / 60), $abs % 60);
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
        $cursor = $from->startOfDay();
        $end = $to->startOfDay();
        $days = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $days[] = $cursor;
            $cursor = $cursor->addDay();
        }

        return $days;
    }
}
