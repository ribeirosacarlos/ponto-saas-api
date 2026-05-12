<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Http\Middleware\EnsurePlanFeature;
use App\Jobs\NormalizeTimeEntriesForOperationalDayJob;
use App\Models\Company;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Bus;
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

        $adjustmentId = $response->json('id');

        $this->assertDatabaseHas('time_entries', [
            'id' => $entry->id,
            'clocked_at' => $entry->clocked_at->toDateTimeString(),
        ]);

        $this->assertDatabaseHas('time_entries', [
            'id' => $adjustmentId,
            'adjustment_status' => 'pending',
            'clocked_at' => $proposed->toDateTimeString(),
        ]);
    }

    public function test_adjustment_type_follows_in_out_cycle(): void
    {
        $this->seedRoles();
        $company = $this->createSubscribedCompany();

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $entry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->startOfDay()->addHours(7),
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->startOfDay()->addHours(10),
            'type' => 'out',
            'source' => 'web',
        ]);

        $adjustmentTime = Carbon::now()->startOfDay()->addHours(15);

        $response = $this->actingAs($employee)
            ->postJson("/v1/employee/time-entries/{$entry->id}/adjustment", [
                'proposed_clocked_at' => $adjustmentTime->toDateTimeString(),
                'reason' => 'Entrada extra',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['type' => 'in']);
    }

    public function test_adjustment_dispatches_async_day_normalization(): void
    {
        Bus::fake();

        $this->seedRoles();
        $company = $this->createSubscribedCompany();

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $entry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->startOfDay()->addHours(18),
            'type' => 'in',
            'source' => 'web',
        ]);

        $this->actingAs($employee)
            ->postJson("/v1/employee/time-entries/{$entry->id}/adjustment", [
                'proposed_clocked_at' => Carbon::now()->startOfDay()->addHours(8)->toDateTimeString(),
                'reason' => 'Corrigir entrada',
            ])
            ->assertStatus(201);

        Bus::assertDispatched(NormalizeTimeEntriesForOperationalDayJob::class);
    }

    public function test_adjustment_is_created_with_provisional_type_based_on_chronological_position(): void
    {
        Bus::fake();

        $this->seedRoles();
        $company = $this->createSubscribedCompany();

        $employee = User::factory()->create([
            'company_id' => $company->id,
        ]);
        $employee->assignRole('employee');

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->startOfDay()->addHours(8)->addMinutes(47),
            'type' => 'in',
            'source' => 'web',
        ]);

        $entry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->startOfDay()->addHours(19)->addMinutes(54),
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->startOfDay()->addHours(12)->addMinutes(49),
            'type' => 'out',
            'source' => 'web',
        ]);

        $response = $this->actingAs($employee)
            ->postJson("/v1/employee/time-entries/{$entry->id}/adjustment", [
                'proposed_clocked_at' => Carbon::now()->startOfDay()->addHours(11)->addMinutes(49)->toDateTimeString(),
                'reason' => 'Corrigir saída do almoço',
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment([
            'type' => 'out',
        ]);
    }

    public function test_employee_entries_exclude_rejected_adjustments(): void
    {
        $this->seedRoles();
        $company = $this->createSubscribedCompany();

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->subDays(1),
            'type' => 'in',
            'source' => 'web',
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->subHours(5),
            'type' => 'out',
            'source' => 'web',
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Pendência',
            'adjustment_requested_by' => $employee->id,
            'adjustment_requested_at' => now(),
        ]);

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->subHours(2),
            'type' => 'in',
            'source' => 'web',
            'adjustment_status' => 'rejected',
            'adjustment_reason' => 'Rejeitado',
            'adjustment_requested_by' => $employee->id,
            'adjustment_requested_at' => now(),
        ]);

        $response = $this->actingAs($employee)
            ->getJson('/v1/employee/entries');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        foreach ($data as $entry) {
            $this->assertTrue(
                empty($entry['adjustment_status']) || $entry['adjustment_status'] !== 'rejected'
            );
        }
    }

    public function test_admin_can_approve_adjustment(): void
    {
        $this->seedRoles();
        $company = $this->createSubscribedCompany();

        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        $manager = User::factory()->create(['company_id' => $company->id]);
        $manager->assignRole('area_manager');

        $proposedClockedAt = Carbon::now()->subHour();

        $adjustmentEntry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => $proposedClockedAt,
            'type' => 'out',
            'source' => 'web',
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Corrigir saída',
            'adjustment_requested_by' => $employee->id,
            'adjustment_requested_at' => now(),
        ]);

        $response = $this->actingAs($manager)
            ->postJson("/v1/admin/time-entries/{$adjustmentEntry->id}/adjustment/approve", [
                'review_reason' => 'Aprovado',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'adjustment_status' => 'approved',
            'adjustment_review_reason' => 'Aprovado',
        ]);

        $this->assertDatabaseHas('time_entries', [
            'id' => $adjustmentEntry->id,
            'adjustment_status' => 'approved',
            'adjustment_review_reason' => 'Aprovado',
            'clocked_at' => $proposedClockedAt->toDateTimeString(),
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

        $adjustmentEntry = TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => Carbon::now()->subHours(3),
            'type' => 'in',
            'source' => 'web',
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Corrigir saída',
            'adjustment_requested_by' => $employee->id,
            'adjustment_requested_at' => now(),
        ]);

        $response = $this->actingAs($manager)
            ->postJson("/v1/admin/time-entries/{$adjustmentEntry->id}/adjustment/reject", [
                'review_reason' => 'Rejeitado',
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'adjustment_status' => 'rejected',
            'adjustment_review_reason' => 'Rejeitado',
        ]);

        $this->assertDatabaseHas('time_entries', [
            'id' => $adjustmentEntry->id,
            'adjustment_status' => 'rejected',
            'adjustment_review_reason' => 'Rejeitado',
        ]);
    }

    public function test_report_respects_adjustment_status_field(): void
    {
        $this->seedRoles();
        $company = $this->createSubscribedCompany();

        $this->withoutMiddleware(EnsurePlanFeature::class);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'clocked_at' => Carbon::now()->subDays(1),
            'type' => 'in',
            'source' => 'web',
            'adjustment_status' => 'pending',
            'adjustment_reason' => 'Ajuste em avaliação',
            'adjustment_requested_by' => $admin->id,
            'adjustment_requested_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/v1/admin/reports/time?start=' . Carbon::now()->subDays(2)->toDateString() . '&end=' . Carbon::now()->toDateString());

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('adjustment_status', $data[0]);
    }
}
