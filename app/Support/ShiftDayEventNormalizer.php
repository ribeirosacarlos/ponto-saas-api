<?php

namespace App\Support;

use App\Models\ShiftDay;

class ShiftDayEventNormalizer
{
    /**
     * @return array<int, array{kind: string, expected_time: string, day_offset: int, expected_type: string, sort_order: int}>
     */
    public static function fromShiftDay(ShiftDay $shiftDay): array
    {
        $persisted = $shiftDay->relationLoaded('events')
            ? $shiftDay->events
            : $shiftDay->events()->orderBy('sort_order')->get();

        if ($persisted->isNotEmpty()) {
            return $persisted->map(fn ($event) => [
                'kind' => $event->kind,
                'expected_time' => self::normalizeTime($event->expected_time),
                'day_offset' => (int) $event->day_offset,
                'expected_type' => $event->expected_type,
                'sort_order' => (int) $event->sort_order,
            ])->all();
        }

        return self::fromLegacyColumns($shiftDay);
    }

    /**
     * @return array<int, array{kind: string, expected_time: string, day_offset: int, expected_type: string, sort_order: int}>
     */
    public static function fromLegacyColumns(ShiftDay $shiftDay): array
    {
        if (! $shiftDay->is_working_day || ! $shiftDay->start_time || ! $shiftDay->end_time) {
            return [];
        }

        $startTime = self::normalizeTime($shiftDay->start_time);
        $endTime = self::normalizeTime($shiftDay->end_time);
        $isOvernight = self::timeToSeconds($endTime) < self::timeToSeconds($startTime);

        $events = [
            [
                'kind' => 'work_start',
                'expected_time' => $startTime,
                'day_offset' => 0,
                'expected_type' => 'in',
                'priority' => 10,
            ],
            [
                'kind' => 'work_end',
                'expected_time' => $endTime,
                'day_offset' => $isOvernight ? 1 : 0,
                'expected_type' => 'out',
                'priority' => 40,
            ],
        ];

        if ($shiftDay->break_start_time && $shiftDay->break_end_time) {
            $breakStart = self::normalizeTime($shiftDay->break_start_time);
            $breakEnd = self::normalizeTime($shiftDay->break_end_time);

            $events[] = [
                'kind' => 'break_start',
                'expected_time' => $breakStart,
                'day_offset' => $isOvernight && self::timeToSeconds($breakStart) < self::timeToSeconds($startTime) ? 1 : 0,
                'expected_type' => 'out',
                'priority' => 20,
            ];

            $events[] = [
                'kind' => 'break_end',
                'expected_time' => $breakEnd,
                'day_offset' => $isOvernight && self::timeToSeconds($breakEnd) < self::timeToSeconds($startTime) ? 1 : 0,
                'expected_type' => 'in',
                'priority' => 30,
            ];
        }

        usort($events, static function (array $left, array $right): int {
            return [$left['day_offset'], $left['expected_time'], $left['priority']]
                <=> [$right['day_offset'], $right['expected_time'], $right['priority']];
        });

        return array_map(static function (array $event, int $index): array {
            return [
                'kind' => $event['kind'],
                'expected_time' => $event['expected_time'],
                'day_offset' => $event['day_offset'],
                'expected_type' => $event['expected_type'],
                'sort_order' => ($index + 1) * 10,
            ];
        }, $events, array_keys($events));
    }

    public static function normalizeTime(string $time): string
    {
        $parts = explode(':', $time);

        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);
        $second = (int) ($parts[2] ?? 0);

        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }

    public static function timeToSeconds(string $time): int
    {
        [$hour, $minute, $second] = array_map('intval', explode(':', self::normalizeTime($time)));

        return ($hour * 3600) + ($minute * 60) + $second;
    }
}
