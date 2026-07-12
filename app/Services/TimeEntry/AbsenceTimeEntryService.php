<?php

namespace App\Services\TimeEntry;

use App\Models\Absence;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AbsenceTimeEntryService
{
    private const SOURCE = 'absence_allowance';

    public function syncForAbsence(Absence $absence): void
    {
        DB::transaction(function () use ($absence) {
            $absence = $absence->fresh('user.company');

            if (! $absence?->user) {
                return;
            }

            $this->deleteGeneratedEntriesForAbsence($absence);

            if (! in_array($absence->status, Absence::EFFECTIVE_STATUSES, true)) {
                return;
            }

            if (! $absence->isFullDayCoverage() && ! $absence->isHoursCoverage()) {
                return;
            }

            $employee = $absence->user;
            $timezone = $employee->company?->timezone ?: config('app.timezone', 'UTC');
            $shiftAssignments = $this->fetchShiftAssignments($employee);
            $defaultShift = $this->fetchDefaultShift($employee);

            $start = CarbonImmutable::parse($absence->start_date, $timezone)->startOfDay();
            $end = CarbonImmutable::parse($absence->end_date ?? $absence->start_date, $timezone)->startOfDay();

            for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
                [$shiftDay, $assignment] = $this->resolveShiftDay($day, $shiftAssignments, $defaultShift);

                if (! $shiftDay || ! $shiftDay->start_time || ! $shiftDay->end_time) {
                    continue;
                }

                foreach ($this->targetIntervals($absence, $day, $shiftDay, $timezone) as [$targetStart, $targetEnd]) {
                    foreach ($this->splitAroundBreak($targetStart, $targetEnd, $shiftDay, $day, $timezone) as $segment) {
                        $remaining = $this->subtractRealCoverage($employee, $segment['start'], $segment['end'], $timezone);
                        $lastIndex = array_key_last($remaining);

                        foreach ($remaining as $index => [$entryStart, $entryEnd]) {
                            $startKind = ($index === 0 && $entryStart->equalTo($segment['start']) && $segment['touchesBreakEnd'])
                                ? 'break_end' : 'work_start';
                            $endKind = ($index === $lastIndex && $entryEnd->equalTo($segment['end']) && $segment['touchesBreakStart'])
                                ? 'break_start' : 'work_end';

                            $this->createGeneratedPair($absence, $employee, $assignment, $entryStart, $entryEnd, $startKind, $endKind);
                        }
                    }
                }
            }
        });
    }

    public function deleteGeneratedEntriesForAbsence(Absence $absence): void
    {
        TimeEntry::withTrashed()
            ->where('absence_id', $absence->id)
            ->where('source', self::SOURCE)
            ->forceDelete();
    }

    /**
     * @return \Illuminate\Support\Collection<int, UserShift>
     */
    private function fetchShiftAssignments(User $employee)
    {
        return $employee->userShifts()
            ->with(['shift.shiftDays' => fn ($query) => $query->orderBy('weekday')])
            ->orderBy('start_date')
            ->get();
    }

    private function fetchDefaultShift(User $employee): ?Shift
    {
        if (! $employee->company_id) {
            return null;
        }

        return Shift::query()
            ->where('company_id', $employee->company_id)
            ->where('is_default', true)
            ->with(['shiftDays' => fn ($query) => $query->orderBy('weekday')])
            ->first();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, UserShift>  $assignments
     * @return array{0: ?ShiftDay, 1: ?UserShift}
     */
    private function resolveShiftDay(CarbonImmutable $date, $assignments, ?Shift $defaultShift): array
    {
        $dateString = $date->toDateString();
        $assignmentForDate = null;
        $shift = null;

        foreach ($assignments as $assignment) {
            $start = $assignment->start_date ? $assignment->start_date->toDateString() : null;
            $end = $assignment->end_date ? $assignment->end_date->toDateString() : null;

            if ($start && $start > $dateString) {
                continue;
            }

            if ($end && $end < $dateString) {
                continue;
            }

            $assignmentForDate = $assignment;
            $shift = $assignment->shift;
            break;
        }

        $shift ??= $defaultShift;

        if (! $shift) {
            return [null, null];
        }

        $shiftDay = $shift->shiftDays->firstWhere('weekday', $date->isoWeekday());

        if (! $shiftDay || ! $shiftDay->is_working_day) {
            return [null, $assignmentForDate];
        }

        return [$shiftDay, $assignmentForDate];
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function targetIntervals(Absence $absence, CarbonImmutable $day, ShiftDay $shiftDay, string $timezone): array
    {
        $shiftStart = $this->dateTimeFromDateAndTime($day, $shiftDay->start_time, $timezone);
        $shiftEnd = $this->dateTimeFromDateAndTime($day, $shiftDay->end_time, $timezone);

        if ($shiftEnd->lessThanOrEqualTo($shiftStart)) {
            $shiftEnd = $shiftEnd->addDay();
        }

        if ($absence->isFullDayCoverage()) {
            return [[$shiftStart, $shiftEnd]];
        }

        if (! $absence->start_time || ! $absence->end_time) {
            return [];
        }

        $absenceStart = $this->dateTimeFromDateAndTime($day, $absence->start_time, $timezone);
        $absenceEnd = $this->dateTimeFromDateAndTime($day, $absence->end_time, $timezone);

        if ($absenceEnd->lessThanOrEqualTo($absenceStart)) {
            $absenceEnd = $absenceEnd->addDay();
        }

        $start = $absenceStart->greaterThan($shiftStart) ? $absenceStart : $shiftStart;
        $end = $absenceEnd->lessThan($shiftEnd) ? $absenceEnd : $shiftEnd;

        if ($end->lessThanOrEqualTo($start)) {
            return [];
        }

        return [[$start, $end]];
    }

    private function breakWindow(ShiftDay $shiftDay, CarbonImmutable $day, string $timezone): ?array
    {
        if (! $shiftDay->break_start_time || ! $shiftDay->break_end_time) {
            return null;
        }

        $breakStart = $this->dateTimeFromDateAndTime($day, $shiftDay->break_start_time, $timezone);
        $breakEnd = $this->dateTimeFromDateAndTime($day, $shiftDay->break_end_time, $timezone);

        if ($breakEnd->lessThanOrEqualTo($breakStart)) {
            $breakEnd = $breakEnd->addDay();
        }

        return [$breakStart, $breakEnd];
    }

    /**
     * @return array<int, array{start: CarbonImmutable, end: CarbonImmutable, touchesBreakStart: bool, touchesBreakEnd: bool}>
     */
    private function splitAroundBreak(CarbonImmutable $start, CarbonImmutable $end, ShiftDay $shiftDay, CarbonImmutable $day, string $timezone): array
    {
        $breakWindow = $this->breakWindow($shiftDay, $day, $timezone);

        if ($breakWindow === null
            || $breakWindow[1]->lessThanOrEqualTo($start)
            || $breakWindow[0]->greaterThanOrEqualTo($end)) {
            return [['start' => $start, 'end' => $end, 'touchesBreakStart' => false, 'touchesBreakEnd' => false]];
        }

        [$breakStart, $breakEnd] = $breakWindow;
        $effectiveStart = $breakStart->greaterThan($start) ? $breakStart : $start;
        $effectiveEnd = $breakEnd->lessThan($end) ? $breakEnd : $end;

        $segments = [];
        if ($effectiveStart->greaterThan($start)) {
            $segments[] = ['start' => $start, 'end' => $effectiveStart, 'touchesBreakStart' => true, 'touchesBreakEnd' => false];
        }
        if ($effectiveEnd->lessThan($end)) {
            $segments[] = ['start' => $effectiveEnd, 'end' => $end, 'touchesBreakStart' => false, 'touchesBreakEnd' => true];
        }

        return $segments;
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function subtractRealCoverage(User $employee, CarbonImmutable $targetStart, CarbonImmutable $targetEnd, string $timezone): array
    {
        $realIntervals = $this->realClosedIntervals($employee, $targetStart, $targetEnd, $timezone);
        $remaining = [[$targetStart, $targetEnd]];

        foreach ($realIntervals as [$realStart, $realEnd]) {
            $next = [];

            foreach ($remaining as [$left, $right]) {
                if ($realEnd->lessThanOrEqualTo($left) || $realStart->greaterThanOrEqualTo($right)) {
                    $next[] = [$left, $right];

                    continue;
                }

                if ($realStart->greaterThan($left)) {
                    $next[] = [$left, $realStart];
                }

                if ($realEnd->lessThan($right)) {
                    $next[] = [$realEnd, $right];
                }
            }

            $remaining = $next;
        }

        return array_values(array_filter(
            $remaining,
            fn (array $interval) => $interval[1]->greaterThan($interval[0])
        ));
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function realClosedIntervals(User $employee, CarbonImmutable $targetStart, CarbonImmutable $targetEnd, string $timezone): array
    {
        $windowStart = $targetStart->startOfDay();

        $entries = TimeEntry::query()
            ->where('company_id', $employee->company_id)
            ->where('user_id', $employee->id)
            ->whereNull('absence_id')
            ->where(function ($query) {
                $query->whereNull('source')
                    ->orWhere('source', '!=', self::SOURCE);
            })
            ->whereIn('type', ['in', 'out'])
            ->where(function ($query) {
                $query->whereNull('adjustment_status')
                    ->orWhere('adjustment_status', '!=', 'rejected');
            })
            ->whereBetween('clocked_at', [
                $windowStart->toDateTimeString(),
                $targetEnd->toDateTimeString(),
            ])
            ->orderBy('clocked_at')
            ->orderBy('created_at')
            ->get();

        $intervals = [];
        $pendingIn = null;

        foreach ($entries as $entry) {
            $clockedAt = CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone);

            if ($entry->type === 'in') {
                $pendingIn = $clockedAt;

                continue;
            }

            if ($pendingIn === null || $clockedAt->lessThanOrEqualTo($pendingIn)) {
                $pendingIn = null;

                continue;
            }

            $start = $pendingIn->greaterThan($targetStart) ? $pendingIn : $targetStart;
            $end = $clockedAt->lessThan($targetEnd) ? $clockedAt : $targetEnd;

            if ($end->greaterThan($start)) {
                $intervals[] = [$start, $end];
            }

            $pendingIn = null;
        }

        return $intervals;
    }

    private function createGeneratedPair(
        Absence $absence,
        User $employee,
        ?UserShift $assignment,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $startKind = 'work_start',
        string $endKind = 'work_end'
    ): void {
        TimeEntry::create([
            'company_id' => $absence->company_id,
            'user_id' => $employee->id,
            'user_shift_id' => $assignment?->id,
            'absence_id' => $absence->id,
            'clocked_at' => $start->toDateTimeString(),
            'type' => 'in',
            'event_kind' => $startKind,
            'source' => self::SOURCE,
            'device_type' => 'system',
        ]);

        TimeEntry::create([
            'company_id' => $absence->company_id,
            'user_id' => $employee->id,
            'user_shift_id' => $assignment?->id,
            'absence_id' => $absence->id,
            'clocked_at' => $end->toDateTimeString(),
            'type' => 'out',
            'event_kind' => $endKind,
            'source' => self::SOURCE,
            'device_type' => 'system',
        ]);
    }

    private function dateTimeFromDateAndTime(CarbonImmutable $date, string $time, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse(sprintf('%s %s', $date->toDateString(), substr($time, 0, 5)), $timezone);
    }
}
