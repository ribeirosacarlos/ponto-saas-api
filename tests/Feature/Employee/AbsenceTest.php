<?php

namespace Tests\Feature\Employee;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Absence;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->seedRoles();
    }

    public function test_employee_can_list_own_absences(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');
        $otherEmployee = $this->createUser($company, 'employee');

        $absence = Absence::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'type' => 'absence',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
        ]);

        Absence::create([
            'company_id' => $company->id,
            'user_id' => $otherEmployee->id,
            'type' => 'absence',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->subDays(3)->toDateString(),
        ]);

        $response = $this->actingAs($employee)->getJson('/v1/employee/absences');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total']);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($absence->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_employee_can_filter_absences_by_date_range(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');

        $absenceInRange = Absence::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'type' => 'absence',
            'start_date' => '2026-01-10',
            'end_date' => '2026-01-12',
        ]);

        Absence::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'type' => 'absence',
            'start_date' => '2025-06-01',
            'end_date' => '2025-06-03',
        ]);

        $response = $this->actingAs($employee)->getJson('/v1/employee/absences?from=2026-01-01&to=2026-01-31');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($absenceInRange->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_unauthenticated_user_cannot_list_absences(): void
    {
        $this->getJson('/v1/employee/absences')->assertUnauthorized();
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
