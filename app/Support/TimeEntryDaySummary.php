<?php

namespace App\Support;

use Illuminate\Support\Arr;

class TimeEntryDaySummary
{
    /**
     * @var array<int, string>
     */
    private const ROOT_ALIAS_KEYS = [
        'worked_minutes',
        'worked_hhmm',
        'raw_worked_minutes',
        'raw_worked_hhmm',
        'actual_worked_minutes',
        'actual_worked_hhmm',
        'expected_minutes',
        'expected_hhmm',
        'real_break_minutes',
        'real_break_hhmm',
        'actual_break_minutes',
        'actual_break_hhmm',
        'allowed_break_minutes',
        'allowed_break_hhmm',
        'counted_break_minutes',
        'counted_break_hhmm',
        'exceeded_break_minutes',
        'exceeded_break_hhmm',
        'balance_minutes',
        'balance_hhmm',
        'extra_minutes',
        'extra_hhmm',
        'debt_minutes',
        'debt_hhmm',
        'status',
        'is_finalized',
        'is_holiday',
        'holiday_name',
        'is_day_off',
        'is_vacation',
        'is_absence',
        'absence_type',
        'has_incomplete_entries',
        'open_session',
    ];

    /**
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>
     */
    public static function normalize(?array $summary): array
    {
        $summary ??= [];

        return [
            'worked_minutes' => (int) ($summary['worked_minutes'] ?? 0),
            'worked_hhmm' => (string) ($summary['worked_hhmm'] ?? '00:00'),
            'raw_worked_minutes' => (int) ($summary['raw_worked_minutes'] ?? 0),
            'raw_worked_hhmm' => (string) ($summary['raw_worked_hhmm'] ?? '00:00'),
            'actual_worked_minutes' => (int) ($summary['actual_worked_minutes'] ?? ($summary['raw_worked_minutes'] ?? 0)),
            'actual_worked_hhmm' => (string) ($summary['actual_worked_hhmm'] ?? ($summary['raw_worked_hhmm'] ?? '00:00')),
            'expected_minutes' => (int) ($summary['expected_minutes'] ?? 0),
            'expected_hhmm' => (string) ($summary['expected_hhmm'] ?? '00:00'),
            'real_break_minutes' => (int) ($summary['real_break_minutes'] ?? 0),
            'real_break_hhmm' => (string) ($summary['real_break_hhmm'] ?? '00:00'),
            'actual_break_minutes' => (int) ($summary['actual_break_minutes'] ?? ($summary['real_break_minutes'] ?? 0)),
            'actual_break_hhmm' => (string) ($summary['actual_break_hhmm'] ?? ($summary['real_break_hhmm'] ?? '00:00')),
            'allowed_break_minutes' => (int) ($summary['allowed_break_minutes'] ?? 0),
            'allowed_break_hhmm' => (string) ($summary['allowed_break_hhmm'] ?? '00:00'),
            'counted_break_minutes' => (int) ($summary['counted_break_minutes'] ?? 0),
            'counted_break_hhmm' => (string) ($summary['counted_break_hhmm'] ?? '00:00'),
            'exceeded_break_minutes' => (int) ($summary['exceeded_break_minutes'] ?? 0),
            'exceeded_break_hhmm' => (string) ($summary['exceeded_break_hhmm'] ?? '00:00'),
            'balance_minutes' => (int) ($summary['balance_minutes'] ?? 0),
            'balance_hhmm' => (string) ($summary['balance_hhmm'] ?? '00:00'),
            'extra_minutes' => (int) ($summary['extra_minutes'] ?? 0),
            'extra_hhmm' => (string) ($summary['extra_hhmm'] ?? '00:00'),
            'debt_minutes' => (int) ($summary['debt_minutes'] ?? 0),
            'debt_hhmm' => (string) ($summary['debt_hhmm'] ?? '00:00'),
            'status' => (string) ($summary['status'] ?? 'even'),
            'is_finalized' => (bool) ($summary['is_finalized'] ?? true),
            'is_holiday' => (bool) ($summary['is_holiday'] ?? false),
            'holiday_name' => $summary['holiday_name'] ?? null,
            'is_day_off' => (bool) ($summary['is_day_off'] ?? false),
            'is_vacation' => (bool) ($summary['is_vacation'] ?? false),
            'is_absence' => (bool) ($summary['is_absence'] ?? false),
            'absence_type' => $summary['absence_type'] ?? null,
            'has_incomplete_entries' => (bool) ($summary['has_incomplete_entries'] ?? false),
            'open_session' => (bool) ($summary['open_session'] ?? false),
            'pair_count' => (int) ($summary['pair_count'] ?? 0),
            'pair_details' => array_values($summary['pair_details'] ?? []),
            'open_pair' => $summary['open_pair'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public static function rootAliases(array $summary): array
    {
        return Arr::only($summary, self::ROOT_ALIAS_KEYS);
    }
}
