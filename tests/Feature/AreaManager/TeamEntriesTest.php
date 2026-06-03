<?php

namespace Tests\Feature\AreaManager;

use App\Models\Company;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamEntriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        foreach (['admin', 'employee'] as $roleName) {
            Role::updateOrCreate(
                ['name' => $roleName],
                ['display_name' => ucfirst($roleName)]
            );
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_team_entries_groups_entries_by_day_for_specific_user(): void
    {
        $company = Company::factory()->create([
            'timezone' => 'UTC',
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $date = CarbonImmutable::parse('2025-12-19', 'UTC');
        $this->assignShift($employee, $date);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(8, 0),
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(17, 0),
            'type' => 'out',
            'source' => 'web',
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/v1/area-manager/team/entries?user_id={$employee->id}");

        $response->assertOk()
            ->assertJsonPath('total_days', 1)
            ->assertJsonPath('total_entries', 2)
            ->assertJsonPath('data.0.date', '2025-12-19')
            ->assertJsonPath('data.0.employee_id', (string) $employee->id)
            ->assertJsonPath('data.0.user.id', (string) $employee->id)
            ->assertJsonPath('data.0.day_summary.worked_minutes', 540)
            ->assertJsonPath('data.0.day_summary.worked_hhmm', '09:00')
            ->assertJsonPath('data.0.day_summary.expected_minutes', 540)
            ->assertJsonPath('data.0.day_summary.balance_minutes', 0)
            ->assertJsonPath('data.0.day_summary.status', 'even')
            ->assertJsonCount(2, 'data.0.entries')
            ->assertJsonPath('data.0.entries.0.id', $employee->timeEntries()->orderByDesc('clocked_at')->first()->id)
            ->assertJsonPath('data.0.entries.0.work_date', '2025-12-19')
            ->assertJsonMissingPath('data.0.entries.0.day_summary');
    }

    public function test_team_entries_ignores_time_component_in_date_filters(): void
    {
        $company = Company::factory()->create([
            'timezone' => 'UTC',
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $date = CarbonImmutable::parse('2025-12-19', 'UTC');
        $this->assignShift($employee, $date);

        $morningEntry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(8, 0),
            'type' => 'in',
            'source' => 'web',
        ]);

        $eveningEntry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(17, 0),
            'type' => 'out',
            'source' => 'web',
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/v1/area-manager/team/entries?user_id={$employee->id}&date_from=2025-12-19T12:00:00Z&date_to=2025-12-19T18:00:00Z");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(2, 'data.0.entries')
            ->assertJsonPath('data.0.entries.0.id', $eveningEntry->id)
            ->assertJsonPath('data.0.entries.1.id', $morningEntry->id);
    }

    public function test_team_entries_returns_all_entries_for_a_specific_user_without_paging_cutoff(): void
    {
        $company = Company::factory()->create([
            'timezone' => 'UTC',
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $date = CarbonImmutable::parse('2025-12-01', 'UTC');
        $this->assignShift($employee, $date);

        for ($i = 0; $i < 31; $i++) {
            TimeEntry::create([
                'company_id' => $company->id,
                'user_id' => $employee->id,
                'clocked_at' => $date->setTime(8, 0)->addMinutes($i),
                'type' => 'in',
                'source' => 'web',
            ]);
        }

        $response = $this->actingAs($admin)
            ->getJson("/v1/area-manager/team/entries?user_id={$employee->id}&page=2");

        $response->assertOk()
            ->assertJsonPath('total_days', 1)
            ->assertJsonPath('total_entries', 31)
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(31, 'data.0.entries');
    }

    public function test_team_entries_day_summary_does_not_add_allowed_break_to_worked_time(): void
    {
        $company = Company::factory()->create([
            'timezone' => 'America/Sao_Paulo',
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $date = CarbonImmutable::parse('2026-05-19', 'America/Sao_Paulo');
        $this->assignShift($employee, $date, [
            'start_time' => '12:53',
            'end_time' => '18:55',
            'scheduled_minutes' => 348,
            'break_minutes' => 20,
            'break_start_time' => '17:08',
            'break_end_time' => '17:28',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(12, 53, 10)->utc(),
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(17, 8, 30)->utc(),
            'type' => 'out',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(17, 22, 55)->utc(),
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(18, 55, 47)->utc(),
            'type' => 'out',
            'source' => 'web',
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/v1/area-manager/team/entries?user_id={$employee->id}&date_from=2026-04-01T00:00:00-03:00&date_to=2026-05-30T23:59:59-03:00");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(4, 'data.0.entries')
            ->assertJsonPath('data.0.day_summary.worked_minutes', 348)
            ->assertJsonPath('data.0.day_summary.worked_hhmm', '05:48')
            ->assertJsonPath('data.0.day_summary.raw_worked_minutes', 348)
            ->assertJsonPath('data.0.day_summary.actual_worked_minutes', 348)
            ->assertJsonPath('data.0.day_summary.real_break_minutes', 14)
            ->assertJsonPath('data.0.day_summary.actual_break_minutes', 14)
            ->assertJsonPath('data.0.day_summary.counted_break_minutes', 14)
            ->assertJsonPath('data.0.day_summary.allowed_break_minutes', 20)
            ->assertJsonPath('data.0.day_summary.extra_minutes', 0)
            ->assertJsonPath('data.0.day_summary.balance_minutes', 0)
            ->assertJsonPath('data.0.day_summary.status', 'even');
    }

    public function test_team_entries_day_summary_subtracts_exceeded_break_from_worked_time(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-07 09:00:00', 'America/Sao_Paulo'));

        $company = Company::factory()->create([
            'timezone' => 'America/Sao_Paulo',
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $date = CarbonImmutable::parse('2026-05-06', 'America/Sao_Paulo');
        $this->assignShift($employee, $date, [
            'start_time' => '11:38',
            'end_time' => '17:38',
            'scheduled_minutes' => 340,
            'break_minutes' => 20,
            'break_start_time' => '16:56',
            'break_end_time' => '17:16',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(11, 38)->utc(),
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(16, 56)->utc(),
            'type' => 'out',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(17, 22)->utc(),
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(18, 56)->utc(),
            'type' => 'out',
            'source' => 'web',
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/v1/area-manager/team/entries?user_id={$employee->id}&date_from=2026-05-06T00:00:00-03:00&date_to=2026-05-06T23:59:59-03:00");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.day_summary.raw_worked_minutes', 412)
            ->assertJsonPath('data.0.day_summary.raw_worked_hhmm', '06:52')
            ->assertJsonPath('data.0.day_summary.real_break_minutes', 26)
            ->assertJsonPath('data.0.day_summary.real_break_hhmm', '00:26')
            ->assertJsonPath('data.0.day_summary.allowed_break_minutes', 20)
            ->assertJsonPath('data.0.day_summary.counted_break_minutes', 20)
            ->assertJsonPath('data.0.day_summary.exceeded_break_minutes', 6)
            ->assertJsonPath('data.0.day_summary.exceeded_break_hhmm', '00:06')
            ->assertJsonPath('data.0.day_summary.worked_minutes', 406)
            ->assertJsonPath('data.0.day_summary.worked_hhmm', '06:46')
            ->assertJsonPath('data.0.day_summary.expected_minutes', 340)
            ->assertJsonPath('data.0.day_summary.balance_minutes', 66)
            ->assertJsonPath('data.0.day_summary.balance_hhmm', '+01:06')
            ->assertJsonPath('data.0.day_summary.extra_minutes', 66)
            ->assertJsonPath('data.0.day_summary.extra_hhmm', '01:06')
            ->assertJsonPath('data.0.day_summary.status', 'extra');
    }

    public function test_team_entries_does_not_close_overtime_for_current_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-19 19:30:00', 'America/Sao_Paulo'));

        $company = Company::factory()->create([
            'timezone' => 'America/Sao_Paulo',
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $date = CarbonImmutable::parse('2026-05-19', 'America/Sao_Paulo');
        $this->assignShift($employee, $date, [
            'start_time' => '12:53',
            'end_time' => '18:55',
            'scheduled_minutes' => 348,
            'break_minutes' => 20,
            'break_start_time' => '17:08',
            'break_end_time' => '17:28',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(12, 53, 10)->utc(),
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(17, 8, 30)->utc(),
            'type' => 'out',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(17, 22, 55)->utc(),
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $date->setTime(18, 55, 47)->utc(),
            'type' => 'out',
            'source' => 'web',
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/v1/area-manager/team/entries?user_id={$employee->id}&date_from=2026-05-19&date_to=2026-05-19");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(4, 'data.0.entries')
            ->assertJsonPath('data.0.day_summary.worked_minutes', 348)
            ->assertJsonPath('data.0.day_summary.extra_minutes', 0)
            ->assertJsonPath('data.0.day_summary.balance_minutes', 0)
            ->assertJsonPath('data.0.day_summary.debt_minutes', 0)
            ->assertJsonPath('data.0.day_summary.status', 'even')
            ->assertJsonPath('data.0.day_summary.is_finalized', false);
    }

    private function assignShift(User $user, CarbonImmutable $date, array $options = []): void
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => 'Team Entries Shift',
            'start_time' => $options['start_time'] ?? '08:00',
            'end_time' => $options['end_time'] ?? '17:00',
            'is_flexible' => false,
            'is_default' => true,
        ]);

        ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => $date->isoWeekday(),
            'is_working_day' => true,
            'start_time' => $options['start_time'] ?? '08:00',
            'end_time' => $options['end_time'] ?? '17:00',
            'scheduled_minutes' => $options['scheduled_minutes'] ?? 540,
            'break_minutes' => $options['break_minutes'] ?? 60,
            'break_start_time' => $options['break_start_time'] ?? '12:00',
            'break_end_time' => $options['break_end_time'] ?? '13:00',
        ]);

        UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => $date->startOfMonth()->toDateString(),
        ]);
    }
}
