<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        DB::table('shift_days')->orderBy('created_at')->chunkById(200, function ($days) use ($now) {
            foreach ($days as $day) {
                if (! $day->is_working_day) {
                    continue;
                }

                if (! $day->start_time || ! $day->end_time) {
                    continue;
                }

                $events = $this->buildEvents($day);

                foreach ($events as $event) {
                    DB::table('shift_day_events')->insert([
                        'id' => (string) Str::uuid(),
                        'shift_day_id' => $day->id,
                        'kind' => $event['kind'],
                        'expected_time' => $event['expected_time'],
                        'day_offset' => $event['day_offset'],
                        'expected_type' => $event['expected_type'],
                        'sort_order' => $event['sort_order'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }, 'id');
    }

    public function down(): void
    {
        DB::table('shift_day_events')->delete();
    }

    /**
     * @return array<int, array{kind: string, expected_time: string, day_offset: int, expected_type: string, sort_order: int}>
     */
    private function buildEvents(object $shiftDay): array
    {
        $startTime = $this->normalizeTime($shiftDay->start_time);
        $endTime = $this->normalizeTime($shiftDay->end_time);
        $isOvernight = $this->timeToSeconds($endTime) < $this->timeToSeconds($startTime);

        $candidates = [
            [
                'kind' => 'work_start',
                'expected_time' => $startTime,
                'expected_type' => 'in',
                'day_offset' => 0,
                'priority' => 10,
            ],
            [
                'kind' => 'work_end',
                'expected_time' => $endTime,
                'expected_type' => 'out',
                'day_offset' => $isOvernight ? 1 : 0,
                'priority' => 40,
            ],
        ];

        if ($shiftDay->break_start_time && $shiftDay->break_end_time) {
            $breakStart = $this->normalizeTime($shiftDay->break_start_time);
            $breakEnd = $this->normalizeTime($shiftDay->break_end_time);

            $candidates[] = [
                'kind' => 'break_start',
                'expected_time' => $breakStart,
                'expected_type' => 'out',
                'day_offset' => $isOvernight && $this->timeToSeconds($breakStart) < $this->timeToSeconds($startTime) ? 1 : 0,
                'priority' => 20,
            ];

            $candidates[] = [
                'kind' => 'break_end',
                'expected_time' => $breakEnd,
                'expected_type' => 'in',
                'day_offset' => $isOvernight && $this->timeToSeconds($breakEnd) < $this->timeToSeconds($startTime) ? 1 : 0,
                'priority' => 30,
            ];
        }

        usort($candidates, function (array $left, array $right): int {
            return [$left['day_offset'], $left['expected_time'], $left['priority']]
                <=> [$right['day_offset'], $right['expected_time'], $right['priority']];
        });

        return array_map(function (array $event, int $index): array {
            return [
                'kind' => $event['kind'],
                'expected_time' => $event['expected_time'],
                'day_offset' => $event['day_offset'],
                'expected_type' => $event['expected_type'],
                'sort_order' => ($index + 1) * 10,
            ];
        }, $candidates, array_keys($candidates));
    }

    private function normalizeTime(string $time): string
    {
        $parts = explode(':', $time);

        $hour = (int) ($parts[0] ?? 0);
        $minute = (int) ($parts[1] ?? 0);
        $second = (int) ($parts[2] ?? 0);

        return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    }

    private function timeToSeconds(string $time): int
    {
        [$hour, $minute, $second] = array_map('intval', explode(':', $time));

        return ($hour * 3600) + ($minute * 60) + $second;
    }
};
