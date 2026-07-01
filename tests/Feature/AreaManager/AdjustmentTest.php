<?php

namespace Tests\Feature\AreaManager;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Company;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);
        $this->seedRoles();
    }

    public function test_area_manager_can_list_pending_adjustments(): void
    {
        $company = Company::factory()->create();
        $areaManager = $this->createUser($company, 'area_manager');
        $employee = $this->createUser($company, 'employee');

        $entry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'type' => 'in',
            'event_kind' => 'work_start',
            'clocked_at' => Carbon::now()->subHours(2),
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Fora do turno',
            'adjustment_requested_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($areaManager)->getJson('/v1/area-manager/adjustments');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_area_manager_can_filter_adjustments_by_status(): void
    {
        $company = Company::factory()->create();
        $areaManager = $this->createUser($company, 'area_manager');
        $employee = $this->createUser($company, 'employee');

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'type' => 'in',
            'event_kind' => 'work_start',
            'clocked_at' => Carbon::now()->subHours(2),
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Fora do turno',
            'adjustment_requested_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($areaManager)->getJson('/v1/area-manager/adjustments?status=pending');

        $response->assertOk();
    }

    public function test_employee_cannot_access_adjustments_list(): void
    {
        $company = Company::factory()->create();
        $employee = $this->createUser($company, 'employee');

        $this->actingAs($employee)
            ->getJson('/v1/area-manager/adjustments')
            ->assertForbidden();
    }

    public function test_adjustments_from_other_companies_are_not_visible(): void
    {
        $company = Company::factory()->create();
        $areaManager = $this->createUser($company, 'area_manager');

        $otherCompany = Company::factory()->create();
        $otherEmployee = $this->createUser($otherCompany, 'employee');

        TimeEntry::create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherEmployee->id,
            'type' => 'in',
            'event_kind' => 'work_start',
            'clocked_at' => Carbon::now()->subHours(2),
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Teste cross-company',
            'adjustment_requested_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($areaManager)->getJson('/v1/area-manager/adjustments');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
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
