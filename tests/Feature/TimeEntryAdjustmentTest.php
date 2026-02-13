<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Http\Middleware\EnsurePlanFeature;
use App\Models\Company;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        Role::create(['name' => 'employee', 'display_name' => 'Employee']);
        Role::create(['name' => 'area_manager', 'display_name' => 'Area Manager']);
        Role::create(['name' => 'manager', 'display_name' => 'Manager']);
        Role::create(['name' => 'admin', 'display_name' => 'Admin']);
    }

    private function createSubscribedCompany(): Company
    {
        return Company::factory()->create([
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
        ]);
    }

    public function test_employee_can_request_adjustment(): void
    {
        $this->seedRoles();
        $company = $this->createSubscribedCompany();

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $entry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->subHour(),
            'type' => 'in',
            'source' => 'web',
        ]);

        $proposed = Carbon::now()->subMinute();

        $response = $this->actingAs($employee)
            ->postJson("/v1/employee/time-entries/{$entry->id}/adjustment", [
                'proposed_clocked_at' => $proposed->toDateTimeString(),
                'reason' => 'Corrigir entrada',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Corrigir entrada',
        ]);

        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'adjustment_status' => 'pending',
            'proposed_clocked_at' => $proposed->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'clocked_at' => $entry->clocked_at->toDateTimeString(),
        ]);
    }

    public function test_second_adjustment_request_is_blocked(): void
    {
        $this->seedRoles();
        $company = $this->createSubscribedCompany();

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $entry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->subHour(),
            'type' => 'in',
            'source' => 'web',
            'adjustment_status' => 'pending',
            'proposed_clocked_at' => Carbon::now()->subMinute(),
            'adjustment_reason' => 'Ainda pendente',
            'adjustment_requested_by' => $employee->id,
            'adjustment_requested_at' => now(),
        ]);

        $this->actingAs($employee)
            ->postJson("/v1/employee/time-entries/{$entry->id}/adjustment", [
                'proposed_clocked_at' => Carbon::now()->toDateTimeString(),
                'reason' => 'Novo motivo',
            ])
            ->assertStatus(409);
    }

    public function test_admin_can_approve_adjustment(): void
    {
        $this->seedRoles();
        $company = $this->createSubscribedCompany();

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $manager = User::factory()->create(['company_id' => $company->id]);
        $manager->assignRole('area_manager');

        $entry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->subHours(3),
            'type' => 'in',
            'source' => 'web',
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Corrigir saída',
            'adjustment_requested_by' => $employee->id,
            'adjustment_requested_at' => now(),
            'proposed_clocked_at' => Carbon::now()->subHour(),
            'proposed_type' => 'out',
        ]);

        $response = $this->actingAs($manager)
            ->postJson("/v1/admin/time-entries/{$entry->id}/adjustment/approve", [
                'review_reason' => 'Aprovado',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'adjustment_status' => 'approved',
            'adjustment_review_reason' => 'Aprovado',
        ]);

        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'adjustment_status' => 'approved',
            'adjustment_review_reason' => 'Aprovado',
            'type' => 'out',
        ]);
    }

    public function test_admin_can_reject_adjustment(): void
    {
        $this->seedRoles();
        $company = $this->createSubscribedCompany();

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $manager = User::factory()->create(['company_id' => $company->id]);
        $manager->assignRole('area_manager');

        $entry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->subHours(3),
            'type' => 'in',
            'source' => 'web',
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Corrigir saída',
            'adjustment_requested_by' => $employee->id,
            'adjustment_requested_at' => now(),
            'proposed_clocked_at' => Carbon::now()->subHour(),
            'proposed_type' => 'out',
        ]);

        $response = $this->actingAs($manager)
            ->postJson("/v1/admin/time-entries/{$entry->id}/adjustment/reject", [
                'review_reason' => 'Rejeitado',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'adjustment_status' => 'rejected',
            'adjustment_review_reason' => 'Rejeitado',
        ]);

        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'adjustment_status' => 'rejected',
            'type' => 'in',
            'clocked_at' => $entry->clocked_at->toDateTimeString(),
        ]);
    }

    public function test_report_respects_adjustment_status_field(): void
    {
        $this->seedRoles();
        $company = $this->createSubscribedCompany();

        $this->withoutMiddleware(EnsurePlanFeature::class);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $entry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'clocked_at' => Carbon::now()->subDays(1),
            'type' => 'in',
            'source' => 'web',
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Ajuste em avaliação',
            'proposed_clocked_at' => Carbon::now()->subMinutes(30),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/v1/admin/reports/time?start=' . Carbon::now()->subDays(2)->toDateString() . '&end=' . Carbon::now()->toDateString());

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('adjustment_status', $data[0]);
    }
}
