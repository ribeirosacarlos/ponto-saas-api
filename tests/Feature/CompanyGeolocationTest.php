<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyGeolocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
    }

    public function test_admin_can_update_company_geolocation_settings(): void
    {
        $user = $this->createAdminWithGeolocationPlan();

        $response = $this->actingAs($user)->putJson('/v1/admin/company/geolocation', [
            'required_on_clock' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('feature_available', true)
            ->assertJsonPath('required_on_clock', true);

        $this->assertDatabaseHas('companies', [
            'id' => $user->company_id,
            'geolocation_required' => true,
        ]);
    }

    public function test_admin_cannot_enable_geolocation_without_plan_feature(): void
    {
        $user = $this->createAdminWithoutGeolocationPlan();

        $this->actingAs($user)->putJson('/v1/admin/company/geolocation', [
            'required_on_clock' => true,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'O plano atual da empresa não possui suporte a geolocalização.');
    }

    private function createAdminWithGeolocationPlan(): User
    {
        return $this->createAdminWithPlanFeatures(['geolocation' => true]);
    }

    private function createAdminWithoutGeolocationPlan(): User
    {
        return $this->createAdminWithPlanFeatures([]);
    }

    private function createAdminWithPlanFeatures(array $features): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $plan = Plan::create([
            'name' => 'Plano Teste',
            'slug' => 'plano-teste-' . md5(json_encode($features)),
            'price_cents' => 1000,
            'currency' => 'BRL',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 1,
            'features' => $features,
            'quotas' => [],
        ]);

        $user->company()->update([
            'current_plan_id' => $plan->id,
            'subscription_status' => 'active',
        ]);
        $user->unsetRelation('company');
        $user->refresh();

        return $user;
    }
}
