<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Adjustment;
use App\Models\Company;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdjustmentApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        Role::create(['name' => 'employee', 'display_name' => 'Employee']);
        Role::create(['name' => 'area_manager', 'display_name' => 'Area Manager']);
    }

    private function createUsers(Company $company): array
    {
        $employee = User::factory()->create([
            'company_id' => $company->id,
        ]);
        $employee->assignRole('employee');

        $manager = User::factory()->create([
            'company_id' => $company->id,
        ]);
        $manager->assignRole('area_manager');

        return [$employee, $manager];
    }

    private function createSubscribedCompany(): Company
    {
        return Company::factory()->create([
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
        ]);
    }

    public function test_approving_adjustment_creates_time_entry(): void
    {
        $this->seedRoles();
        $company = $this->createSubscribedCompany();
        [$employee, $manager] = $this->createUsers($company);

        $adjustment = Adjustment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'corrected_time' => Carbon::now()->subDay()->setTime(8, 0),
            'reason' => 'Esqueci de bater o ponto',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($manager)
            ->postJson("/v1/area-manager/adjustments/{$adjustment->id}/approve");

        $response->assertStatus(200);

        $this->assertDatabaseHas('time_entries', [
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $adjustment->corrected_time->toDateTimeString(),
            'type' => 'in',
            'source' => 'adjustment',
        ]);

        $this->assertDatabaseHas('adjustments', [
            'id' => $adjustment->id,
            'status' => 'approved',
        ]);
    }

    public function test_approving_adjustment_closes_open_session(): void
    {
        $this->seedRoles();
        $company = $this->createSubscribedCompany();
        [$employee, $manager] = $this->createUsers($company);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->setTime(8, 0),
            'type' => 'in',
            'source' => 'web',
        ]);

        $correctedTime = Carbon::now()->setTime(17, 0);

        $adjustment = Adjustment::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'corrected_time' => $correctedTime,
            'reason' => 'Consertei a saída',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($manager)
            ->postJson("/v1/area-manager/adjustments/{$adjustment->id}/approve");

        $response->assertStatus(200);

        $this->assertDatabaseHas('time_entries', [
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $correctedTime->toDateTimeString(),
            'type' => 'out',
            'source' => 'adjustment',
        ]);
    }
}
