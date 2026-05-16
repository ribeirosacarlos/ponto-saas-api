<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Plan;
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

class TimeEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_non_working_day_creates_pending_adjustment(): void
    {
        $this->freezeNow('2026-02-16 09:00:00'); // Monday

        $user = $this->createEmployee();
        $this->createShiftDayWithEvents($user, 1, false, []);

        $response = $this->actingAs($user)->postJson('/v1/employee/clock', []);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'adjustment_requested');

        $this->assertDatabaseHas('time_entries', [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Fora do turno/jornada (dia nao trabalhado ou sem jornada).',
        ]);
    }

    public function test_working_day_with_break_first_event_is_in(): void
    {
        $this->freezeNow('2026-02-16 08:00:00');

        $user = $this->createEmployee();
        $this->createShiftDayWithEvents($user, 1, true, $this->breakDayEvents());

        $this->actingAs($user)
            ->postJson('/v1/employee/clock')
            ->assertStatus(201)
            ->assertJsonPath('entry.type', 'in')
            ->assertJsonPath('entry.event_kind', 'work_start');
    }

    public function test_working_day_with_break_second_event_is_out(): void
    {
        $this->freezeNow('2026-02-16 12:00:00');

        $user = $this->createEmployee();
        $assignment = $this->createShiftDayWithEvents($user, 1, true, $this->breakDayEvents());
        $this->seedEntries($user, $assignment->id, [
            ['clocked_at' => '2026-02-16 08:00:00', 'type' => 'in', 'event_kind' => 'work_start'],
        ]);

        $this->actingAs($user)
            ->postJson('/v1/employee/clock')
            ->assertStatus(201)
            ->assertJsonPath('entry.type', 'out')
            ->assertJsonPath('entry.event_kind', 'break_start');
    }

    public function test_working_day_with_break_third_event_is_in(): void
    {
        $this->freezeNow('2026-02-16 13:00:00');

        $user = $this->createEmployee();
        $assignment = $this->createShiftDayWithEvents($user, 1, true, $this->breakDayEvents());
        $this->seedEntries($user, $assignment->id, [
            ['clocked_at' => '2026-02-16 08:00:00', 'type' => 'in', 'event_kind' => 'work_start'],
            ['clocked_at' => '2026-02-16 12:00:00', 'type' => 'out', 'event_kind' => 'break_start'],
        ]);

        $this->actingAs($user)
            ->postJson('/v1/employee/clock')
            ->assertStatus(201)
            ->assertJsonPath('entry.type', 'in')
            ->assertJsonPath('entry.event_kind', 'break_end');
    }

    public function test_working_day_with_break_fourth_event_is_out(): void
    {
        $this->freezeNow('2026-02-16 17:00:00');

        $user = $this->createEmployee();
        $assignment = $this->createShiftDayWithEvents($user, 1, true, $this->breakDayEvents());
        $this->seedEntries($user, $assignment->id, [
            ['clocked_at' => '2026-02-16 08:00:00', 'type' => 'in', 'event_kind' => 'work_start'],
            ['clocked_at' => '2026-02-16 12:00:00', 'type' => 'out', 'event_kind' => 'break_start'],
            ['clocked_at' => '2026-02-16 13:00:00', 'type' => 'in', 'event_kind' => 'break_end'],
        ]);

        $this->actingAs($user)
            ->postJson('/v1/employee/clock')
            ->assertStatus(201)
            ->assertJsonPath('entry.type', 'out')
            ->assertJsonPath('entry.event_kind', 'work_end');
    }

    public function test_working_day_without_break_second_event_is_out(): void
    {
        $this->freezeNow('2026-02-16 17:00:00');

        $user = $this->createEmployee();
        $assignment = $this->createShiftDayWithEvents($user, 1, true, [
            ['kind' => 'work_start', 'time' => '08:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'work_end', 'time' => '17:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
        ]);

        $this->seedEntries($user, $assignment->id, [
            ['clocked_at' => '2026-02-16 08:00:00', 'type' => 'in', 'event_kind' => 'work_start'],
        ]);

        $this->actingAs($user)
            ->postJson('/v1/employee/clock')
            ->assertStatus(201)
            ->assertJsonPath('entry.type', 'out')
            ->assertJsonPath('entry.event_kind', 'work_end');
    }

    public function test_clock_saves_geolocation_when_sent(): void
    {
        $this->freezeNow('2026-02-16 08:00:00');

        $user = $this->createEmployee();
        $this->createShiftDayWithEvents($user, 1, true, $this->breakDayEvents());

        $this->actingAs($user)
            ->postJson('/v1/employee/clock', [
                'latitude' => '-23.55052',
                'longitude' => '-46.633308',
                'source' => 'mobile',
            ])
            ->assertStatus(201)
            ->assertJsonPath('entry.latitude', '-23.550520')
            ->assertJsonPath('entry.longitude', '-46.633308');

        $this->assertDatabaseHas('time_entries', [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'latitude' => '-23.550520',
            'longitude' => '-46.633308',
            'source' => 'mobile',
        ]);
    }

    public function test_clock_requires_geolocation_when_company_setting_is_enabled(): void
    {
        $this->freezeNow('2026-02-16 08:00:00');

        $user = $this->createEmployeeWithGeolocationPlan([
            'geolocation_required' => true,
        ]);
        $this->createShiftDayWithEvents($user, 1, true, $this->breakDayEvents());

        $this->actingAs($user)
            ->postJson('/v1/employee/clock', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_admin_first_clock_counts_as_billable_employee_for_plan_usage(): void
    {
        $this->freezeNow('2026-02-16 08:00:00');

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = Plan::create([
            'slug' => 'time-entry-billable-admin',
            'name' => 'Plano ponto admin',
            'description' => 'Plano para contar admin que bate ponto.',
            'price_cents' => 1000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
            'quotas' => ['max_employees' => 0],
            'extra_employee_price_cents' => 500,
            'stripe_price_id' => 'price_base',
            'stripe_extra_employee_price_id' => 'price_extra',
        ]);

        $admin->company()->update(['current_plan_id' => $plan->id]);
        $this->createShiftDayWithEvents($admin, 1, true, $this->breakDayEvents());

        $this->actingAs($admin)
            ->postJson('/v1/employee/clock')
            ->assertStatus(201)
            ->assertJsonPath('entry.type', 'in');

        $this->assertDatabaseHas('extra_employee_charges', [
            'company_id' => $admin->company_id,
            'status' => 'pending',
            'quantity' => 1,
        ]);
    }

    /**
     * @return array<int, array{kind: string, time: string, day_offset: int, expected_type: string}>
     */
    private function breakDayEvents(): array
    {
        return [
            ['kind' => 'work_start', 'time' => '08:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'break_start', 'time' => '12:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
            ['kind' => 'break_end', 'time' => '13:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'work_end', 'time' => '17:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
        ];
    }

    public function test_clock_blocked_for_mobile_when_mobile_is_disabled(): void
    {
        $this->freezeNow('2026-02-16 09:00:00'); // Monday

        $user = $this->createEmployee();
        $user->company()->update(['allow_mobile_clock' => false]);
        $user->unsetRelation('company');
        $this->createShiftDayWithEvents($user, 1, true, $this->breakDayEvents());

        $response = $this->actingAs($user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 10) Mobile Safari/537.36'])
            ->postJson('/v1/employee/clock', []);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Registro de ponto não permitido neste dispositivo (mobile).');
    }

    public function test_clock_blocked_for_desktop_when_desktop_is_disabled(): void
    {
        $this->freezeNow('2026-02-16 09:00:00'); // Monday

        $user = $this->createEmployee();
        $user->company()->update(['allow_desktop_clock' => false]);
        $user->unsetRelation('company');
        $this->createShiftDayWithEvents($user, 1, true, $this->breakDayEvents());

        $response = $this->actingAs($user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
            ->postJson('/v1/employee/clock', []);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Registro de ponto não permitido neste dispositivo (desktop).');
    }

    public function test_clock_allowed_for_mobile_with_default_settings(): void
    {
        $this->freezeNow('2026-02-16 09:00:00'); // Monday

        $user = $this->createEmployee();
        $this->createShiftDayWithEvents($user, 1, true, $this->breakDayEvents());

        $response = $this->actingAs($user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Linux; Android 10) Mobile Safari/537.36'])
            ->postJson('/v1/employee/clock', []);

        $response->assertCreated();
    }

    public function test_clock_allowed_for_desktop_with_default_settings(): void
    {
        $this->freezeNow('2026-02-16 09:00:00'); // Monday

        $user = $this->createEmployee();
        $this->createShiftDayWithEvents($user, 1, true, $this->breakDayEvents());

        $response = $this->actingAs($user)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
            ->postJson('/v1/employee/clock', []);

        $response->assertCreated();
    }

    private function createEmployee(): User
    {
        $user = User::factory()->create();
        $user->assignRole('employee');

        return $user;
    }

    private function createEmployeeWithGeolocationPlan(array $companyOverrides = []): User
    {
        $user = $this->createEmployee();
        $plan = Plan::create([
            'name' => 'Plano com Geo',
            'slug' => 'geo-required',
            'price_cents' => 1000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [
                'geolocation' => true,
            ],
            'quotas' => [],
        ]);

        $user->company()->update(array_merge([
            'current_plan_id' => $plan->id,
        ], $companyOverrides));
        $user->unsetRelation('company');
        $user->refresh();

        return $user;
    }

    /**
     * @param  array<int, array{kind: string, time: string, day_offset: int, expected_type: string}>  $events
     */
    private function createShiftDayWithEvents(User $user, int $weekday, bool $isWorkingDay, array $events): UserShift
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => 'Shift ' . Str::random(4),
            'is_default' => false,
            'is_flexible' => false,
            'start_time' => collect($events)->firstWhere('kind', 'work_start')['time'] ?? null,
            'end_time' => collect($events)->firstWhere('kind', 'work_end')['time'] ?? null,
        ]);

        $shiftDay = ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => $weekday,
            'is_working_day' => $isWorkingDay,
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

        return UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);
    }

    /**
     * @param  array<int, array{clocked_at: string, type: string, event_kind: string}>  $entries
     */
    private function seedEntries(User $user, string $assignmentId, array $entries): void
    {
        foreach ($entries as $entry) {
            TimeEntry::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'user_shift_id' => $assignmentId,
                'clocked_at' => CarbonImmutable::parse($entry['clocked_at'], 'Europe/Madrid'),
                'type' => $entry['type'],
                'event_kind' => $entry['event_kind'],
                'source' => 'web',
            ]);
        }
    }

    private function freezeNow(string $datetime): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($datetime, 'Europe/Madrid'));
    }
}
