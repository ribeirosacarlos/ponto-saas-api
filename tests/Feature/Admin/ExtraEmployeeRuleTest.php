<?php

namespace Tests\Feature\Admin;

use App\Actions\Employees\InviteEmployeeAction;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ExtraEmployeeRuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_employee_creation_creates_and_accumulates_pending_extra_charge_up_to_three(): void
    {
        Bus::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = Plan::create([
            'slug' => 'rule-plan',
            'name' => 'Plano regra',
            'description' => 'Plano para testar regra de extra',
            'price_cents' => 1000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
            'quotas' => ['max_employees' => 1],
            'extra_employee_price_cents' => 500,
            'stripe_price_id' => 'price_base',
            'stripe_extra_employee_price_id' => 'price_extra',
        ]);

        $admin->company()->update(['current_plan_id' => $plan->id]);

        $includedEmployee = User::factory()->create(['company_id' => $admin->company_id]);
        $includedEmployee->assignRole('employee');

        $action = app(InviteEmployeeAction::class);

        $action->execute($admin->fresh(), [
            'name' => 'Extra 1',
            'email' => 'extra1@example.com',
            'role' => 'employee',
        ]);
        $this->assertDatabaseHas('extra_employee_charges', [
            'company_id' => $admin->company_id,
            'status' => 'pending',
            'quantity' => 1,
        ]);

        $action->execute($admin->fresh(), [
            'name' => 'Extra 2',
            'email' => 'extra2@example.com',
            'role' => 'employee',
        ]);
        $this->assertDatabaseHas('extra_employee_charges', [
            'company_id' => $admin->company_id,
            'status' => 'pending',
            'quantity' => 2,
        ]);

        $action->execute($admin->fresh(), [
            'name' => 'Extra 3',
            'email' => 'extra3@example.com',
            'role' => 'employee',
        ]);
        $this->assertDatabaseHas('extra_employee_charges', [
            'company_id' => $admin->company_id,
            'status' => 'pending',
            'quantity' => 3,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $action->execute($admin->fresh(), [
            'name' => 'Extra 4',
            'email' => 'extra4@example.com',
            'role' => 'employee',
        ]);
    }

    public function test_overdue_pending_charge_blocks_new_employee_creation(): void
    {
        Bus::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $plan = Plan::create([
            'slug' => 'rule-plan-overdue',
            'name' => 'Plano regra atraso',
            'description' => 'Plano para testar atraso',
            'price_cents' => 1000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'features' => [],
            'quotas' => ['max_employees' => 1],
            'extra_employee_price_cents' => 500,
            'stripe_price_id' => 'price_base',
            'stripe_extra_employee_price_id' => 'price_extra',
        ]);

        $admin->company()->update(['current_plan_id' => $plan->id]);

        $includedEmployee = User::factory()->create(['company_id' => $admin->company_id]);
        $includedEmployee->assignRole('employee');

        $firstExtra = User::factory()->create(['company_id' => $admin->company_id]);
        $firstExtra->assignRole('employee');

        \App\Models\ExtraEmployeeCharge::create([
            'company_id' => $admin->company_id,
            'plan_id' => $plan->id,
            'quantity' => 1,
            'status' => 'pending',
            'due_at' => now()->subDay(),
        ]);

        $this->inviteEmployee($admin, 'blocked@example.com')
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    protected function inviteEmployee(User $admin, string $email)
    {
        return $this->actingAs($admin->fresh('company', 'roles'))->postJson('/v1/admin/employees', [
            'name' => 'Teste',
            'email' => $email,
            'role' => 'employee',
        ]);
    }
}
