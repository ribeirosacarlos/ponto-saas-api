<?php

namespace Tests\Feature\Employee;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->seedRoles();
    }

    public function test_employee_can_list_own_vacations(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');
        $vacation = $this->createVacationRequest($company, $employee);

        $otherEmployee = $this->createUser($company, 'employee');
        $this->createVacationRequest($company, $otherEmployee);

        $response = $this->actingAs($employee)->getJson('/v1/employee/vacations');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total']);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($vacation->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_employee_can_cancel_own_pending_vacation(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');
        $vacation = $this->createVacationRequest($company, $employee, 'pending');

        $this->actingAs($employee)
            ->deleteJson("/v1/employee/vacations/{$vacation->id}")
            ->assertOk();

        $this->assertDatabaseHas('vacation_requests', [
            'id' => $vacation->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_employee_cannot_cancel_approved_vacation(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');
        $vacation = $this->createVacationRequest($company, $employee, 'approved');

        $this->actingAs($employee)
            ->deleteJson("/v1/employee/vacations/{$vacation->id}")
            ->assertUnprocessable();
    }

    public function test_employee_cannot_cancel_another_employees_vacation(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');
        $otherEmployee = $this->createUser($company, 'employee');
        $vacation = $this->createVacationRequest($company, $otherEmployee, 'pending');

        $this->actingAs($employee)
            ->deleteJson("/v1/employee/vacations/{$vacation->id}")
            ->assertForbidden();
    }

    public function test_employee_can_view_vacation_balance(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');

        $response = $this->actingAs($employee)->getJson('/v1/employee/vacations/balance');

        $response->assertOk()
            ->assertJsonStructure([
                'period_start',
                'period_end',
                'accrued_days',
                'used_days',
                'available_days',
            ]);
    }

    private function createVacationRequest(Company $company, User $employee, string $status = 'pending'): VacationRequest
    {
        return VacationRequest::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'requested_days' => 10,
            'counting_method_snapshot' => 'calendar_days',
            'status' => $status,
            'requested_by' => $employee->id,
        ]);
    }

    private function createUser(Company $company, string $role): User
    {
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->syncRoles([$role]);

        // Re-fetch from DB so $user->id is a string (not a UUID object from factory)
        return User::find($user->id);
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
