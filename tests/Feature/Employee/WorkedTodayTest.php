<?php

namespace Tests\Feature\Employee;

use App\Enums\SubscriptionStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\Subscription;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkedTodayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(
            ['name' => 'employee'],
            ['display_name' => 'Employee']
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_pending_departure_does_not_close_or_count_today_work(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-12-19 19:00:00', 'UTC'));
        $user = $this->createEmployee();
        $this->assignShift($user);
        $this->createTimeEntry($user, 'in', CarbonImmutable::parse('2025-12-19 08:00:00', 'UTC'));
        TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => '2025-12-19 18:00:00',
            'type' => 'out',
            'source' => 'adjustment',
            'adjustment_status' => 'pending',
        ]);

        $this->actingAs($user)->getJson('/v1/employee/worked-today')
            ->assertOk()
            ->assertJsonPath('data.worked_minutes', 0)
            ->assertJsonPath('data.open_session', true)
            ->assertJsonPath('data.summary.extra_minutes', 0)
            ->assertJsonPath('data.summary.is_finalized', false)
            ->assertJsonPath('data.summary.has_incomplete_entries', true)
            ->assertJsonCount(0, 'data.details.pairs')
            ->assertJsonCount(2, 'data.details.entries')
            ->assertJsonFragment(['adjustment_status' => 'pending']);
    }

    public function test_returns_zero_hours_when_no_entries()
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-12-19 12:00:00', 'UTC'));

        $user = $this->createEmployee();
        $this->assignShift($user, [
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
            'break_minutes' => 60,
        ]);

        $response = $this->actingAs($user)->getJson('/v1/employee/worked-today');

        $response->assertStatus(200)
            ->assertJsonPath('data.worked_seconds', 0)
            ->assertJsonPath('data.worked_minutes', 0)
            ->assertJsonPath('data.worked_hhmm', '00:00')
            ->assertJsonPath('data.worked_hours_decimal', 0)
            ->assertJsonPath('data.expected_break_minutes', 60)
            ->assertJsonPath('data.break_seconds_deducted', 0)
            ->assertJsonPath('data.open_session', false)
            ->assertJsonPath('data.summary.worked_minutes', 0)
            ->assertJsonPath('data.summary.worked_hhmm', '00:00');

        $response->assertJsonCount(0, 'data.details.pairs');
    }

    public function test_counts_open_session_until_now()
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-12-19 15:00:00', 'UTC'));

        $user = $this->createEmployee();
        $this->assignShift($user, [
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
            'break_minutes' => 60,
        ]);

        $this->createTimeEntry($user, 'in', CarbonImmutable::parse('2025-12-19 08:00:00', 'UTC'));

        $response = $this->actingAs($user)->getJson('/v1/employee/worked-today');

        // clocked_at é o horário local da empresa; "08:00:00" naive já representa
        // 08:00 em config('app.timezone') (timezone da empresa), sem conversão adicional.
        $expectedOpenPairIn = CarbonImmutable::parse('2025-12-19 08:00:00', config('app.timezone') ?? 'UTC')
            ->toIso8601String();

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.details.pairs')
            ->assertJsonPath('data.details.open_pair.in', $expectedOpenPairIn)
            ->assertJson([
                'data' => [
                    'worked_seconds' => 0,
                    'worked_minutes' => 0,
                    'worked_hours_decimal' => 0,
                    'expected_break_minutes' => 60,
                    'break_seconds_deducted' => 0,
                    'open_session' => true,
                ],
            ]);
    }

    public function test_counts_closed_pairs_when_there_is_an_open_pair()
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-12-19 15:00:00', 'UTC'));

        $user = $this->createEmployee();
        $this->assignShift($user, [
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
            'break_minutes' => 60,
        ]);

        $this->createTimeEntry($user, 'in', CarbonImmutable::parse('2025-12-19 08:00:00', 'UTC'));
        $this->createTimeEntry($user, 'out', CarbonImmutable::parse('2025-12-19 12:00:00', 'UTC'));
        $this->createTimeEntry($user, 'in', CarbonImmutable::parse('2025-12-19 13:00:00', 'UTC'));

        $response = $this->actingAs($user)->getJson('/v1/employee/worked-today');

        // clocked_at é o horário local da empresa; "13:00:00" naive já representa
        // 13:00 em config('app.timezone') (timezone da empresa), sem conversão adicional.
        $expectedOpenPairIn = CarbonImmutable::parse('2025-12-19 13:00:00', config('app.timezone') ?? 'UTC')
            ->toIso8601String();

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.details.pairs')
            ->assertJsonPath('data.details.pairs.0.seconds', 14400)
            ->assertJsonPath('data.details.open_pair.in', $expectedOpenPairIn)
            ->assertJson([
                'data' => [
                    'worked_seconds' => 14400,
                    'worked_minutes' => 240,
                    'worked_hhmm' => '04:00',
                    'worked_hours_decimal' => 4.0,
                    'expected_break_minutes' => 60,
                    'break_seconds_deducted' => 0,
                    'open_session' => true,
                ],
            ]);
    }

    public function test_calculates_total_when_entry_and_exit_are_present()
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-12-19 18:00:00', 'UTC'));

        $user = $this->createEmployee();
        $this->assignShift($user, [
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
            'break_minutes' => 60,
        ]);

        $this->createTimeEntry($user, 'in', CarbonImmutable::parse('2025-12-19 08:00:00', 'UTC'));
        $this->createTimeEntry($user, 'out', CarbonImmutable::parse('2025-12-19 17:00:00', 'UTC'));

        $response = $this->actingAs($user)->getJson('/v1/employee/worked-today');

        $response->assertStatus(200)
            ->assertJsonPath('data.details.pairs.0.seconds', 32400)
            ->assertJson([
                'data' => [
                    'worked_seconds' => 32400,
                    'worked_minutes' => 540,
                    'worked_hhmm' => '09:00',
                    'worked_hours_decimal' => 9.0,
                    'expected_break_minutes' => 60,
                    'break_seconds_deducted' => 0,
                    'open_session' => false,
                ],
            ]);
    }

    public function test_uses_explicit_interval_entries_for_break_deduction()
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-12-19 18:00:00', 'UTC'));

        $user = $this->createEmployee();
        $this->assignShift($user, [
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
            'break_minutes' => 60,
        ]);

        $this->createTimeEntry($user, 'in', CarbonImmutable::parse('2025-12-19 08:00:00', 'UTC'));
        $this->createTimeEntry($user, 'break_start', CarbonImmutable::parse('2025-12-19 12:00:00', 'UTC'));
        $this->createTimeEntry($user, 'break_end', CarbonImmutable::parse('2025-12-19 13:00:00', 'UTC'));
        $this->createTimeEntry($user, 'out', CarbonImmutable::parse('2025-12-19 17:00:00', 'UTC'));

        $response = $this->actingAs($user)->getJson('/v1/employee/worked-today');

        $response->assertStatus(200)
            ->assertJsonPath('data.details.pairs.0.seconds', 32400)
            ->assertJson([
                'data' => [
                    'worked_seconds' => 32400,
                    'worked_minutes' => 540,
                    'worked_hhmm' => '09:00',
                    'worked_hours_decimal' => 9.0,
                    'expected_break_minutes' => 60,
                    'break_seconds_deducted' => 0,
                    'open_session' => false,
                ],
            ]);
    }

    public function test_applies_scheduled_break_when_crossing_break_window()
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-12-19 14:00:00', 'UTC'));

        $user = $this->createEmployee();
        $this->assignShift($user, [
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
            'break_minutes' => 60,
        ]);

        $this->createTimeEntry($user, 'in', CarbonImmutable::parse('2025-12-19 08:30:00', 'UTC'));
        $this->createTimeEntry($user, 'out', CarbonImmutable::parse('2025-12-19 12:30:00', 'UTC'));

        $response = $this->actingAs($user)->getJson('/v1/employee/worked-today');

        $response->assertStatus(200)
            ->assertJsonPath('data.details.pairs.0.seconds', 14400)
            ->assertJson([
                'data' => [
                    'worked_seconds' => 14400,
                    'worked_minutes' => 240,
                    'worked_hours_decimal' => 4.0,
                    'expected_break_minutes' => 60,
                    'break_seconds_deducted' => 0,
                    'open_session' => false,
                ],
            ]);
    }

    public function test_returns_all_registered_entries_including_adjustments(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-12-19 18:00:00', 'UTC'));

        $user = $this->createEmployee();
        $this->assignShift($user, [
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
            'break_minutes' => 60,
        ]);

        $normal = TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => CarbonImmutable::parse('2025-12-19 08:00:00', 'UTC'),
            'type' => 'in',
            'source' => 'web',
        ]);

        $pendingAdjustment = TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => CarbonImmutable::parse('2025-12-19 12:00:00', 'UTC'),
            'type' => 'out',
            'source' => 'adjustment',
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Ajuste pendente',
            'adjustment_requested_by' => $user->id,
            'adjustment_requested_at' => now(),
        ]);

        $rejectedAdjustment = TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => CarbonImmutable::parse('2025-12-19 13:00:00', 'UTC'),
            'type' => 'in',
            'source' => 'adjustment',
            'adjustment_status' => 'rejected',
            'adjustment_reason' => 'Ajuste rejeitado',
            'adjustment_requested_by' => $user->id,
            'adjustment_requested_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/v1/employee/worked-today');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.details.entries')
            ->assertJsonPath('data.details.entries.0.id', $normal->id)
            ->assertJsonPath('data.details.entries.1.id', $pendingAdjustment->id)
            ->assertJsonPath('data.details.entries.1.adjustment_status', 'pending')
            ->assertJsonPath('data.details.entries.2.id', $rejectedAdjustment->id)
            ->assertJsonPath('data.details.entries.2.adjustment_status', 'rejected');
    }

    private function createEmployee(): User
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        $this->ensureActiveSubscription($user);

        return $user;
    }

    private function assignShift(User $user, array $options = []): Shift
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => $options['name'] ?? 'Teste Jornada',
            'start_time' => $options['start_time'] ?? '08:00',
            'end_time' => $options['end_time'] ?? '17:00',
            'is_flexible' => $options['is_flexible'] ?? false,
            'is_default' => $options['is_default'] ?? true,
        ]);

        $breakMinutes = $options['break_minutes']
            ?? (! empty($options['break_start_time']) || ! empty($options['break_end_time']) ? 60 : 0);

        ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => CarbonImmutable::now()->isoWeekday(),
            'is_working_day' => $options['is_working_day'] ?? true,
            'start_time' => $options['start_time'] ?? $shift->start_time,
            'end_time' => $options['end_time'] ?? $shift->end_time,
            'break_start_time' => $options['break_start_time'] ?? '12:00',
            'break_end_time' => $options['break_end_time'] ?? '13:00',
            'break_minutes' => $breakMinutes,
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

    private function ensureActiveSubscription(User $user): void
    {
        $plan = Plan::firstOrCreate(
            ['slug' => 'worked-today-test'],
            [
                'name' => 'Worked Today Test Plan',
                'description' => 'Plano de teste',
                'price_cents' => 1000,
                'currency' => 'USD',
                'billing_interval' => 'month',
                'trial_days' => 0,
                'is_active' => true,
                'sort_order' => 1,
                'features' => [],
                'quotas' => [],
            ]
        );

        Subscription::updateOrCreate(
            ['company_id' => $user->company_id],
            [
                'company_id' => $user->company_id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::ACTIVE,
                'current_period_start' => CarbonImmutable::now()->startOfMonth(),
                'current_period_end' => CarbonImmutable::now()->endOfMonth(),
                'metadata' => [],
            ]
        );
    }
}
