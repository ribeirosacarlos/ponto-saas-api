<?php

namespace Tests\Feature\Employee;

use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Role;
use App\Models\UserShift;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpenStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::updateOrCreate(
            ['name' => 'employee'],
            ['display_name' => 'Employee']
        );
    }

    public function test_returns_clock_in_when_no_entries()
    {
        $user = $this->createEmployee();
        $this->assignShift($user);

        $response = $this->actingAs($user)->getJson('/api/v1/employee/time-entries/open-status');

        $response->assertStatus(200)
            ->assertJson([
                'has_open_entry' => false,
                'open_type' => null,
                'next_action' => 'clock_in',
                'shift' => [
                    'start' => '08:00',
                    'end' => '17:00',
                    'tolerance_minutes' => 10,
                ],
            ]);

        $this->assertNull($response->json('last_entry'));
    }

    public function test_reports_open_work_when_last_entry_is_in()
    {
        $user = $this->createEmployee();
        $this->assignShift($user, ['break_start_time' => null, 'break_end_time' => null]);
        $this->createTimeEntry($user, 'in', CarbonImmutable::now()->subHour());

        $response = $this->actingAs($user)->getJson('/api/v1/employee/time-entries/open-status');

        $response->assertStatus(200)
            ->assertJson([
                'has_open_entry' => true,
                'open_type' => 'work',
                'next_action' => 'clock_out',
                'last_entry' => [
                    'type' => 'in',
                ],
            ]);
    }

    public function test_returns_clock_in_after_completing_entries()
    {
        $user = $this->createEmployee();
        $this->assignShift($user);
        $this->createTimeEntry($user, 'in', CarbonImmutable::now()->setTime(8, 0));
        $this->createTimeEntry($user, 'out', CarbonImmutable::now()->setTime(17, 0));

        $response = $this->actingAs($user)->getJson('/api/v1/employee/time-entries/open-status');

        $response->assertStatus(200)
            ->assertJson([
                'has_open_entry' => false,
                'open_type' => null,
                'next_action' => 'clock_in',
                'last_entry' => [
                    'type' => 'out',
                ],
            ]);
    }

    public function test_detects_open_break_when_break_started_without_ending()
    {
        $user = $this->createEmployee();
        $this->assignShift($user, [
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
        ]);
        $this->createTimeEntry($user, 'in', CarbonImmutable::now()->setTime(8, 0));
        $this->createTimeEntry($user, 'break_start', CarbonImmutable::now()->setTime(12, 0));

        $response = $this->actingAs($user)->getJson('/api/v1/employee/time-entries/open-status');

        $response->assertStatus(200)
            ->assertJson([
                'has_open_entry' => true,
                'open_type' => 'break',
                'next_action' => 'break_end',
                'last_entry' => [
                    'type' => 'break_start',
                ],
            ]);
    }

    public function test_shift_endpoint_returns_shift_rules()
    {
        $user = $this->createEmployee();
        $shift = $this->assignShift($user, [
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/employee/shift');

        $response->assertStatus(200);
        $response->assertJsonPath('shift.id', $shift->id);
        $response->assertJsonPath('shift.shift_days.0.weekday', CarbonImmutable::now()->isoWeekday());
        $response->assertJsonPath('shift.shift_days.0.break_start_time', '12:00');
        $response->assertJsonPath('assignment.start_date', CarbonImmutable::now()->toDateString());
        $this->assertNotNull($response->json('assignment.id'));
    }

    private function createEmployee(): User
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        return $user;
    }

    private function assignShift(User $user, array $options = []): Shift
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => 'Teste ' . Str::random(4),
            'start_time' => $options['start_time'] ?? '08:00',
            'end_time' => $options['end_time'] ?? '17:00',
            'is_flexible' => $options['is_flexible'] ?? false,
            'is_default' => $options['is_default'] ?? true,
        ]);

        ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => CarbonImmutable::now()->isoWeekday(),
            'is_working_day' => true,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'break_start_time' => $options['break_start_time'] ?? null,
            'break_end_time' => $options['break_end_time'] ?? null,
        ]);

        UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => CarbonImmutable::now()->toDateString(),
            'end_date' => null,
        ]);

        return $shift;
    }

    private function createTimeEntry(User $user, string $type, CarbonImmutable $clockedAt): TimeEntry
    {
        return TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => $clockedAt,
            'type' => $type,
            'source' => 'web',
        ]);
    }
}
