<?php

namespace Tests\Unit\Services\TimeEntry;

use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use App\Services\TimeEntry\TimeEntryDayNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TimeEntryDayNormalizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_reorders_types_when_missing_earlier_entries_are_added_later(): void
    {
        $user = $this->createEmployee();
        $assignment = $this->createShiftDayWithEvents($user, 1, [
            ['kind' => 'work_start', 'time' => '08:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'break_start', 'time' => '12:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
            ['kind' => 'break_end', 'time' => '13:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'work_end', 'time' => '18:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
        ]);

        $lateExit = TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'user_shift_id' => $assignment->id,
            'clocked_at' => CarbonImmutable::parse('2026-02-16 18:00:00', 'Europe/Madrid'),
            'type' => 'in',
            'event_kind' => 'work_start',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'user_shift_id' => $assignment->id,
            'clocked_at' => CarbonImmutable::parse('2026-02-16 08:00:00', 'Europe/Madrid'),
            'type' => 'in',
            'event_kind' => 'work_start',
            'source' => 'adjustment',
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Corrigir entrada',
            'adjustment_requested_by' => $user->id,
            'adjustment_requested_at' => now(),
        ]);

        TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'user_shift_id' => $assignment->id,
            'clocked_at' => CarbonImmutable::parse('2026-02-16 12:00:00', 'Europe/Madrid'),
            'type' => 'out',
            'event_kind' => 'break_start',
            'source' => 'adjustment',
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Corrigir saída almoço',
            'adjustment_requested_by' => $user->id,
            'adjustment_requested_at' => now(),
        ]);

        TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'user_shift_id' => $assignment->id,
            'clocked_at' => CarbonImmutable::parse('2026-02-16 13:00:00', 'Europe/Madrid'),
            'type' => 'in',
            'event_kind' => 'break_end',
            'source' => 'adjustment',
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Corrigir volta almoço',
            'adjustment_requested_by' => $user->id,
            'adjustment_requested_at' => now(),
        ]);

        app(TimeEntryDayNormalizer::class)->normalizeForReference(
            $user,
            CarbonImmutable::parse('2026-02-16 18:00:00', 'Europe/Madrid')
        );

        $sequence = TimeEntry::query()
            ->where('user_id', $user->id)
            ->orderBy('clocked_at')
            ->get(['clocked_at', 'type', 'event_kind'])
            ->map(fn (TimeEntry $entry) => [
                'clocked_at' => $entry->clocked_at->format('H:i:s'),
                'type' => $entry->type,
                'event_kind' => $entry->event_kind,
            ])
            ->all();

        $this->assertSame([
            ['clocked_at' => '08:00:00', 'type' => 'in', 'event_kind' => 'work_start'],
            ['clocked_at' => '12:00:00', 'type' => 'out', 'event_kind' => 'break_start'],
            ['clocked_at' => '13:00:00', 'type' => 'in', 'event_kind' => 'break_end'],
            ['clocked_at' => '18:00:00', 'type' => 'out', 'event_kind' => 'work_end'],
        ], $sequence);

        $lateExit->refresh();
        $this->assertSame('out', $lateExit->type);
        $this->assertSame('work_end', $lateExit->event_kind);
    }

    public function test_allows_extra_entries_and_marks_them_as_free(): void
    {
        $user = $this->createEmployee();
        $assignment = $this->createShiftDayWithEvents($user, 1, [
            ['kind' => 'work_start', 'time' => '08:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'work_end', 'time' => '17:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
        ]);

        foreach ([
            '08:00:00',
            '12:00:00',
            '12:30:00',
            '17:00:00',
            '18:00:00',
            '18:15:00',
        ] as $clockedAt) {
            TimeEntry::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'user_shift_id' => $assignment->id,
                'clocked_at' => CarbonImmutable::parse("2026-02-16 {$clockedAt}", 'Europe/Madrid'),
                'type' => 'in',
                'event_kind' => 'work_start',
                'source' => 'web',
            ]);
        }

        app(TimeEntryDayNormalizer::class)->normalizeForReference(
            $user,
            CarbonImmutable::parse('2026-02-16 18:15:00', 'Europe/Madrid')
        );

        $sequence = TimeEntry::query()
            ->where('user_id', $user->id)
            ->orderBy('clocked_at')
            ->pluck('event_kind', 'type')
            ->all();

        $ordered = TimeEntry::query()
            ->where('user_id', $user->id)
            ->orderBy('clocked_at')
            ->get(['type', 'event_kind'])
            ->map(fn (TimeEntry $entry) => [$entry->type, $entry->event_kind])
            ->all();

        $this->assertSame([
            ['in', 'work_start'],
            ['out', 'work_end'],
            ['in', 'free'],
            ['out', 'free'],
            ['in', 'free'],
            ['out', 'free'],
        ], $ordered);
    }

    public function test_inserting_missing_lunch_out_rebalances_following_entries(): void
    {
        $user = $this->createEmployee();
        $assignment = $this->createShiftDayWithEvents($user, 1, [
            ['kind' => 'work_start', 'time' => '08:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'break_start', 'time' => '12:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
            ['kind' => 'break_end', 'time' => '13:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'work_end', 'time' => '18:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
        ]);

        foreach ([
            ['08:47:00', 'in', 'work_start'],
            ['12:39:00', 'in', 'break_start'],
            ['13:39:00', 'in', 'break_end'],
            ['18:00:00', 'out', 'work_end'],
        ] as [$clockedAt, $type, $eventKind]) {
            TimeEntry::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'user_shift_id' => $assignment->id,
                'clocked_at' => CarbonImmutable::parse("2026-02-16 {$clockedAt}", 'Europe/Madrid'),
                'type' => $type,
                'event_kind' => $eventKind,
                'source' => 'web',
            ]);
        }

        app(TimeEntryDayNormalizer::class)->normalizeForReference(
            $user,
            CarbonImmutable::parse('2026-02-16 12:39:00', 'Europe/Madrid')
        );

        $ordered = TimeEntry::query()
            ->where('user_id', $user->id)
            ->orderBy('clocked_at')
            ->get(['clocked_at', 'type', 'event_kind'])
            ->map(fn (TimeEntry $entry) => [
                'clocked_at' => $entry->clocked_at->format('H:i:s'),
                'type' => $entry->type,
                'event_kind' => $entry->event_kind,
            ])
            ->all();

        $this->assertSame([
            ['clocked_at' => '08:47:00', 'type' => 'in', 'event_kind' => 'work_start'],
            ['clocked_at' => '12:39:00', 'type' => 'out', 'event_kind' => 'break_start'],
            ['clocked_at' => '13:39:00', 'type' => 'in', 'event_kind' => 'break_end'],
            ['clocked_at' => '18:00:00', 'type' => 'out', 'event_kind' => 'work_end'],
        ], $ordered);
    }

    /**
     * @param  array<int, array{kind: string, time: string, day_offset: int, expected_type: string}>  $events
     */
    private function createShiftDayWithEvents(User $user, int $weekday, array $events): UserShift
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => 'Shift ' . Str::random(5),
            'start_time' => $events[0]['time'] ?? '08:00:00',
            'end_time' => $events[array_key_last($events)]['time'] ?? '17:00:00',
            'is_flexible' => false,
            'is_default' => false,
        ]);

        $shiftDay = ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => $weekday,
            'is_working_day' => true,
            'start_time' => $events[0]['time'] ?? '08:00:00',
            'end_time' => $events[array_key_last($events)]['time'] ?? '17:00:00',
        ]);

        foreach ($events as $index => $event) {
            $shiftDay->events()->create([
                'kind' => $event['kind'],
                'expected_time' => $event['time'],
                'day_offset' => $event['day_offset'],
                'expected_type' => $event['expected_type'],
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        return UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);
    }

    private function createEmployee(): User
    {
        $user = User::factory()->create();
        $user->assignRole('employee');

        return $user;
    }
}
