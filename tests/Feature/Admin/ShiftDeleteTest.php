<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Http\Middleware\EnsureSubscriptionTrialOrActive;
use App\Models\Company;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\ShiftDayEvent;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->withoutMiddleware(EnsureSubscriptionTrialOrActive::class);
        $this->seedRoles();
    }

    public function test_admin_can_delete_shift_without_user_assignments_and_cascade_definition_days(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        [$shift, $shiftDay, $event] = $this->createShiftDefinition($company);

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/shifts/{$shift->id}")
            ->assertOk()
            ->assertJson([
                'message' => 'Deletado',
            ]);

        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
        $this->assertDatabaseMissing('shift_days', ['id' => $shiftDay->id]);
        $this->assertDatabaseMissing('shift_day_events', ['id' => $event->id]);
    }

    public function test_admin_cannot_delete_shift_with_user_assignment_and_time_entries_remain_untouched(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');
        [$shift, $shiftDay, $event] = $this->createShiftDefinition($company);

        $assignment = UserShift::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'start_date' => '2026-06-01',
            'end_date' => null,
        ]);

        $timeEntry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'user_shift_id' => $assignment->id,
            'clocked_at' => '2026-06-03 08:00:00',
            'type' => 'in',
            'event_kind' => 'work_start',
            'source' => 'web',
        ]);

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/shifts/{$shift->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shift'])
            ->assertJsonPath('errors.shift.0', 'Não é possível remover uma jornada vinculada a colaboradores.');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
        $this->assertDatabaseHas('shift_days', ['id' => $shiftDay->id]);
        $this->assertDatabaseHas('shift_day_events', ['id' => $event->id]);
        $this->assertDatabaseHas('user_shifts', ['id' => $assignment->id, 'shift_id' => $shift->id]);
        $this->assertDatabaseHas('time_entries', [
            'id' => $timeEntry->id,
            'user_shift_id' => $assignment->id,
            'deleted_at' => null,
        ]);
    }

    private function createShiftDefinition(Company $company): array
    {
        $shift = Shift::create([
            'company_id' => $company->id,
            'name' => 'Jornada Comercial',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'is_flexible' => false,
            'is_default' => false,
        ]);

        $shiftDay = ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => 1,
            'is_working_day' => true,
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'scheduled_minutes' => 480,
            'break_start_time' => '12:00:00',
            'break_end_time' => '13:00:00',
            'break_minutes' => 60,
        ]);

        $event = ShiftDayEvent::create([
            'shift_day_id' => $shiftDay->id,
            'kind' => 'work_start',
            'expected_time' => '08:00:00',
            'day_offset' => 0,
            'expected_type' => 'in',
            'sort_order' => 1,
        ]);

        return [$shift, $shiftDay, $event];
    }

    private function createUser(Company $company, string $role): User
    {
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function seedRoles(): void
    {
        foreach (['admin', 'manager', 'area_manager', 'employee'] as $roleName) {
            Role::updateOrCreate(
                ['name' => $roleName],
                ['display_name' => ucfirst(str_replace('_', ' ', $roleName))]
            );
        }
    }
}
