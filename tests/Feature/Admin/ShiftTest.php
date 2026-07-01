<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Company;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->seedRoles();
    }

    public function test_admin_can_list_company_shifts(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $shift = Shift::create([
            'company_id' => $company->id,
            'name' => 'Turno Manhã',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_flexible' => false,
            'is_default' => false,
        ]);

        $otherShift = Shift::create([
            'company_id' => Company::factory()->create()->id,
            'name' => 'Turno Outro',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_flexible' => false,
            'is_default' => false,
        ]);

        $response = $this->actingAs($admin)->getJson('/v1/admin/shifts');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($shift->id, $ids);
        $this->assertNotContains($otherShift->id, $ids);
    }

    public function test_admin_can_create_shift(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');

        $response = $this->actingAs($admin)->postJson('/v1/admin/shifts', [
            'name' => 'Turno Padrão',
            'is_flexible' => false,
            'is_default' => false,
            'days' => $this->buildDaysPayload(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('name', 'Turno Padrão');

        $this->assertDatabaseHas('shifts', [
            'company_id' => $company->id,
            'name' => 'Turno Padrão',
        ]);
    }

    public function test_admin_can_show_shift(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $shift = Shift::create([
            'company_id' => $company->id,
            'name' => 'Turno Tarde',
            'start_time' => '14:00',
            'end_time' => '23:00',
            'is_flexible' => false,
            'is_default' => false,
        ]);

        $response = $this->actingAs($admin)->getJson("/v1/admin/shifts/{$shift->id}");

        $response->assertOk()
            ->assertJsonPath('name', 'Turno Tarde');
    }

    public function test_admin_cannot_view_shift_from_other_company(): void
    {
        $admin = $this->createUser(Company::factory()->create(), 'admin');
        $otherShift = Shift::create([
            'company_id' => Company::factory()->create()->id,
            'name' => 'Turno Alheio',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_flexible' => false,
            'is_default' => false,
        ]);

        $this->actingAs($admin)
            ->getJson("/v1/admin/shifts/{$otherShift->id}")
            ->assertStatus(403);
    }

    public function test_employee_cannot_access_shifts(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');

        $this->actingAs($employee)
            ->getJson('/v1/admin/shifts')
            ->assertForbidden();
    }

    private function buildDaysPayload(): array
    {
        $days = [];
        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $isWorking = $weekday <= 5;
            $days[] = [
                'weekday' => $weekday,
                'is_working_day' => $isWorking,
                'start_time' => $isWorking ? '08:00' : null,
                'end_time' => $isWorking ? '17:00' : null,
                'scheduled_minutes' => $isWorking ? 540 : null,
            ];
        }

        return $days;
    }

    private function createUser(Company $company, string $role): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
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
