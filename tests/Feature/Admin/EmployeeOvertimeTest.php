<?php

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionStatus;
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

class EmployeeOvertimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_admin_can_fetch_overtime_summary_with_days(): void
    {
        $company = Company::factory()->create([
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $date = CarbonImmutable::parse('2025-12-15', 'UTC');
        $this->assignShift($employee, $date);

        $this->createTimeEntry($employee, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($employee, 'out', $date->setTime(12, 0));
        $this->createTimeEntry($employee, 'in', $date->setTime(13, 0));
        $this->createTimeEntry($employee, 'out', $date->setTime(18, 0));

        $response = $this->actingAs($admin)->getJson("/v1/admin/employees/{$employee->id}/overtime?from=2025-12-15&to=2025-12-15&include_days=1");

        $response->assertStatus(200);
        $response->assertJsonPath('employee_id', (string) $employee->id);
        $response->assertJsonPath('totals.worked_minutes', 600);
        $response->assertJsonPath('totals.extra_minutes', 60);
        $response->assertJsonPath('days.0.date', '2025-12-15');
        $response->assertJsonPath('days.0.summary.worked_minutes', 600);
        $response->assertJsonPath('days.0.summary.worked_hhmm', '10:00');
        $response->assertJsonPath('days.0.status', 'extra');
        $response->assertJsonPath('days.0.ignored', false);
    }

    public function test_missing_from_parameter_uses_first_entry_or_requested_end_date(): void
    {
        $company = Company::factory()->create([
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $date = CarbonImmutable::parse('2025-12-15', 'UTC');
        $this->assignShift($employee, $date);
        $this->createTimeEntry($employee, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($employee, 'out', $date->setTime(17, 0));

        $response = $this->actingAs($admin)->getJson("/v1/admin/employees/{$employee->id}/overtime?to=2025-12-15");

        $response->assertStatus(200);
        $response->assertJsonPath('from', '2025-12-15');
        $response->assertJsonPath('to', '2025-12-15');
    }

    private function assignShift(User $user, CarbonImmutable $date): void
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => 'Test Shift',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_flexible' => false,
            'is_default' => true,
        ]);

        ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => $date->isoWeekday(),
            'is_working_day' => true,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'scheduled_minutes' => 540,
            'break_minutes' => 60,
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
        ]);

        UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => CarbonImmutable::parse('2025-12-01')->toDateString(),
            'end_date' => null,
        ]);
    }

    private function createTimeEntry(User $user, string $type, CarbonImmutable $clockedAt): void
    {
        TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => $clockedAt,
            'type' => $type,
            'source' => 'web',
        ]);
    }

    private function seedRoles(): void
    {
        Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::firstOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }
}
