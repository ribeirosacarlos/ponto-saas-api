<?php

namespace Tests\Unit\Actions\TimeEntries;

use App\Actions\TimeEntries\ResolveNextExpectedClockAction;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResolveNextExpectedClockActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_resolves_shift_from_assignment_before_company_default(): void
    {
        $user = $this->createEmployee();

        $defaultShift = $this->createShift($user, true, 1, [
            ['kind' => 'work_start', 'time' => '09:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'work_end', 'time' => '18:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
        ]);

        $assignedShift = $this->createShift($user, false, 1, [
            ['kind' => 'work_start', 'time' => '08:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'work_end', 'time' => '17:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
        ]);

        $assignment = UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $assignedShift->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $action = app(ResolveNextExpectedClockAction::class);
        $result = $action->handle($user, CarbonImmutable::parse('2026-02-16 08:15:00', 'Europe/Madrid'));

        $this->assertSame($assignedShift->id, $result['shift']?->id);
        $this->assertSame($assignment->id, $result['assignment']?->id);
        $this->assertSame('work_start', $result['next_event']['kind']);

        $this->assertNotSame($defaultShift->id, $result['shift']?->id);
    }

    public function test_falls_back_to_default_shift_when_assignment_missing(): void
    {
        $user = $this->createEmployee();

        $defaultShift = $this->createShift($user, true, 1, [
            ['kind' => 'work_start', 'time' => '09:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'work_end', 'time' => '18:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
        ]);

        $action = app(ResolveNextExpectedClockAction::class);
        $result = $action->handle($user, CarbonImmutable::parse('2026-02-16 09:10:00', 'Europe/Madrid'));

        $this->assertSame($defaultShift->id, $result['shift']?->id);
        $this->assertNull($result['assignment']);
    }

    public function test_rejected_entries_are_ignored_when_resolving_next_event(): void
    {
        $user = $this->createEmployee();

        $shift = $this->createShift($user, false, 1, [
            ['kind' => 'work_start', 'time' => '08:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'work_end', 'time' => '17:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
        ]);

        UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => CarbonImmutable::parse('2026-02-16 08:00:00', 'Europe/Madrid'),
            'type' => 'in',
            'event_kind' => 'work_start',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => CarbonImmutable::parse('2026-02-16 17:00:00', 'Europe/Madrid'),
            'type' => 'out',
            'event_kind' => 'work_end',
            'source' => 'web',
            'adjustment_status' => 'rejected',
            'adjustment_reason' => 'Rejected adjustment',
            'adjustment_requested_by' => $user->id,
            'adjustment_requested_at' => now(),
        ]);

        $action = app(ResolveNextExpectedClockAction::class);
        $result = $action->handle($user, CarbonImmutable::parse('2026-02-16 17:10:00', 'Europe/Madrid'));

        $this->assertNotNull($result['next_event']);
        $this->assertSame('work_end', $result['next_event']['kind']);
        $this->assertSame('out', $result['next_event']['expected_type']);
    }

    /**
     * @param  array<int, array{kind: string, time: string, day_offset: int, expected_type: string}>  $events
     */
    private function createShift(User $user, bool $isDefault, int $weekday, array $events): Shift
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => 'Shift ' . Str::random(5),
            'start_time' => collect($events)->firstWhere('kind', 'work_start')['time'] ?? null,
            'end_time' => collect($events)->firstWhere('kind', 'work_end')['time'] ?? null,
            'is_flexible' => false,
            'is_default' => $isDefault,
        ]);

        $shiftDay = ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => $weekday,
            'is_working_day' => true,
            'start_time' => collect($events)->firstWhere('kind', 'work_start')['time'] ?? null,
            'end_time' => collect($events)->firstWhere('kind', 'work_end')['time'] ?? null,
            'break_start_time' => collect($events)->firstWhere('kind', 'break_start')['time'] ?? null,
            'break_end_time' => collect($events)->firstWhere('kind', 'break_end')['time'] ?? null,
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

        return $shift;
    }

    private function createEmployee(): User
    {
        $user = User::factory()->create();
        $user->assignRole('employee');

        return $user;
    }
}
