<?php

namespace App\Services\TimeEntry;

use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\User;
use App\Services\UserShiftResolver;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OvertimeCalculatorService
{
    private const WORK_ENTRY_TYPES = ['in', 'out'];

    public function __construct(
        protected UserShiftResolver $shiftResolver
    ) {
    }

    /**
     * @return array{employee_id: string, from: string, to: string, timezone: string, totals: array, days?: array}
     */
    public function calculateForEmployee(User $employee, CarbonImmutable $from, CarbonImmutable $to, bool $includeDays): array
    {
        $timezone = $this->resolveTimezone($employee);
        $fromLocal = $this->normalizeToLocalStart($from, $timezone);
        $toLocal = $this->normalizeToLocalEnd($to, $timezone);

        $entries = $this->fetchEntries($employee, $fromLocal, $toLocal);
        $groupedEntries = $this->groupEntriesByDate($entries, $timezone);
        $shift = $this->shiftResolver->resolve($employee)['shift'];

        $totalWorked = 0;
        $totalExpected = 0;
        $totalExtra = 0;
        $totalDebt = 0;
        $dailyDetails = [];

        foreach ($groupedEntries as $date => $entriesForDay) {
            $expectedMinutes = $this->expectedMinutesForDate($shift, CarbonImmutable::parse($date));
            $pairResult = $this->pairAndSumMinutes($entriesForDay);
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
                    'ignored' => $ignored,
                    'reason' => $reason,
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
            'timezone' => $timezone,
            'totals' => $totals,
            'days' => $includeDays ? $dailyDetails : null,
        ], fn ($value) => $value !== null);
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
     * @return array{worked_minutes?: int, ignored: bool, reason?: string}
     */
    protected function pairAndSumMinutes(array $entries): array
    {
        $count = count($entries);

        if ($count < 2) {
            return ['ignored' => true, 'reason' => 'too_few_entries'];
        }

        if ($count % 2 !== 0) {
            return ['ignored' => true, 'reason' => 'open_day_odd_entries'];
        }

        $workedMinutes = 0;

        for ($i = 0; $i < $count; $i += 2) {
            $in = $entries[$i];
            $out = $entries[$i + 1];

            if ($out->lessThanOrEqualTo($in)) {
                return ['ignored' => true, 'reason' => 'invalid_pairs'];
            }

            $workedMinutes += $out->diffInMinutes($in, true);
        }

        return ['ignored' => false, 'worked_minutes' => $workedMinutes];
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

    protected function expectedMinutesForDate(?Shift $shift, CarbonImmutable $date): int
    {
        if (! $shift) {
            return 0;
        }

        $weekday = $date->isoWeekday();
        $definition = $shift->shiftDays->firstWhere('weekday', $weekday);

        if (! $definition || ! $definition->is_working_day) {
            return 0;
        }

        $start = $this->parseShiftTime($definition->start_time);
        $end = $this->parseShiftTime($definition->end_time);

        if (! $start || ! $end) {
            return 0;
        }

        $duration = max(0, $end->diffInMinutes($start, true));
        $breakMinutes = $this->resolveBreakMinutes($definition);

        return max(0, $duration - $breakMinutes);
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
}
