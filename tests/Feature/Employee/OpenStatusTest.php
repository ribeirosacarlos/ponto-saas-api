<?php

namespace Tests\Feature\Employee;

use App\Http\Middleware\EnsureCompanyHasAccess;
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

class OpenStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_overnight_open_status_is_true_after_30_minutes_from_expected_out(): void
    {
        $user = $this->createEmployee();
        $this->createOvernightShift($user, 1); // Monday 22:00 -> Tuesday 06:00

        TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'user_shift_id' => $user->userShifts()->first()->id,
            'clocked_at' => CarbonImmutable::parse('2026-02-16 22:01:00', 'Europe/Madrid'),
            'type' => 'in',
            'event_kind' => 'work_start',
            'source' => 'web',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-17 06:40:00', 'Europe/Madrid'));

        $response = $this->actingAs($user)->getJson('/v1/employee/time-entries/open-status');

        $response->assertStatus(200)
            ->assertJsonPath('open', true)
            ->assertJsonPath('assignment_id', $user->userShifts()->first()->id)
            ->assertJsonPath('expected_next_out_at', '2026-02-17T06:00:00+01:00');
    }

    public function test_overnight_open_status_is_false_before_30_minutes_from_expected_out(): void
    {
        $user = $this->createEmployee();
        $this->createOvernightShift($user, 1);

        TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'user_shift_id' => $user->userShifts()->first()->id,
            'clocked_at' => CarbonImmutable::parse('2026-02-16 22:01:00', 'Europe/Madrid'),
            'type' => 'in',
            'event_kind' => 'work_start',
            'source' => 'web',
        ]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-02-17 06:20:00', 'Europe/Madrid'));

        $response = $this->actingAs($user)->getJson('/v1/employee/time-entries/open-status');

        $response->assertStatus(200)
            ->assertJsonPath('open', false)
            ->assertJsonPath('open_reason', null)
            ->assertJsonPath('expected_next_out_at', '2026-02-17T06:00:00+01:00');
    }

    private function createEmployee(): User
    {
        $user = User::factory()->create();
        $user->assignRole('employee');

        return $user;
    }

    private function createOvernightShift(User $user, int $weekday): void
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => 'Overnight ' . Str::random(4),
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'is_flexible' => false,
            'is_default' => false,
        ]);

        $shiftDay = ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => $weekday,
            'is_working_day' => true,
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
        ]);

        $shiftDay->events()->createMany([
            [
                'kind' => 'work_start',
                'expected_time' => '22:00:00',
                'day_offset' => 0,
                'expected_type' => 'in',
                'sort_order' => 10,
            ],
            [
                'kind' => 'work_end',
                'expected_time' => '06:00:00',
                'day_offset' => 1,
                'expected_type' => 'out',
                'sort_order' => 20,
            ],
        ]);

        UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);
    }
}
