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

    public function test_team_entries_returns_day_summary_for_each_entry(): void
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
            ->assertJsonPath('data.0.work_date', '2025-12-19')
            ->assertJsonPath('data.0.day_summary.worked_minutes', 540)
            ->assertJsonPath('data.0.day_summary.worked_hhmm', '09:00')
            ->assertJsonPath('data.0.day_summary.expected_minutes', 540)
            ->assertJsonPath('data.0.day_summary.balance_minutes', 0)
            ->assertJsonPath('data.0.day_summary.status', 'even')
            ->assertJsonPath('data.1.work_date', '2025-12-19')
            ->assertJsonPath('data.1.day_summary.worked_minutes', 540)
            ->assertJsonPath('data.1.day_summary.worked_hhmm', '09:00');
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
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $eveningEntry->id)
            ->assertJsonPath('data.1.id', $morningEntry->id);
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
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('total', 31)
            ->assertJsonPath('per_page', 31)
            ->assertJsonCount(31, 'data');
    }

    private function assignShift(User $user, CarbonImmutable $date): void
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => 'Team Entries Shift',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_flexible' => false,
            'is_default' => true,
        ]);

        ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => $date->isoWeekday(),
            'is_working_day' => true,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'scheduled_minutes' => 540,
            'break_minutes' => 60,
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
        ]);

        UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => $date->startOfMonth()->toDateString(),
        ]);
    }
}
