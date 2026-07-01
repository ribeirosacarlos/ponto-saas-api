<?php

namespace Tests\Feature\Admin;

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

    public function test_admin_can_list_vacations(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');
        $vacation = $this->createVacationRequest($company, $employee);

        $response = $this->actingAs($admin)->getJson('/v1/admin/vacations');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total']);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($vacation->id, $ids->toArray());
    }

    public function test_admin_cannot_see_vacations_from_other_company(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');

        $otherCompany = Company::factory()->create();
        $otherEmployee = User::factory()->create(['company_id' => $otherCompany->id]);
        $this->createVacationRequest($otherCompany, $otherEmployee);

        $response = $this->actingAs($admin)->getJson('/v1/admin/vacations');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }

    public function test_admin_can_approve_pending_vacation(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');
        $vacation = $this->createVacationRequest($company, $employee, 'pending');

        $response = $this->actingAs($admin)->postJson("/v1/admin/vacations/{$vacation->id}/approve");

        $response->assertOk();
        $this->assertDatabaseHas('vacation_requests', [
            'id' => $vacation->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_can_reject_pending_vacation(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');
        $vacation = $this->createVacationRequest($company, $employee, 'pending');

        $response = $this->actingAs($admin)->postJson("/v1/admin/vacations/{$vacation->id}/reject", [
            'rejection_reason' => 'Período com muitos colaboradores de férias.',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('vacation_requests', [
            'id' => $vacation->id,
            'status' => 'rejected',
        ]);
    }

    public function test_admin_cannot_approve_already_approved_vacation(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');
        $vacation = $this->createVacationRequest($company, $employee, 'approved');

        $response = $this->actingAs($admin)->postJson("/v1/admin/vacations/{$vacation->id}/approve");

        $response->assertUnprocessable();
    }

    public function test_admin_can_cancel_vacation(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createUser($company, 'admin');
        $employee = $this->createUser($company, 'employee');
        $vacation = $this->createVacationRequest($company, $employee, 'pending');

        $this->actingAs($admin)
            ->deleteJson("/v1/admin/vacations/{$vacation->id}")
            ->assertOk();

        $this->assertDatabaseHas('vacation_requests', [
            'id' => $vacation->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_admin_cannot_manage_vacation_from_other_company(): void
    {
        $admin = $this->createUser(Company::factory()->create(), 'admin');
        $otherCompany = Company::factory()->create();
        $otherEmployee = User::factory()->create(['company_id' => $otherCompany->id]);
        $vacation = $this->createVacationRequest($otherCompany, $otherEmployee, 'pending');

        $this->actingAs($admin)
            ->postJson("/v1/admin/vacations/{$vacation->id}/approve")
            ->assertForbidden();
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
