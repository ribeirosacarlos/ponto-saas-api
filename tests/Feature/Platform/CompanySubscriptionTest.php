<?php

namespace Tests\Feature\Platform;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\StripeBillingService;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class CompanySubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new PlansSeeder())->run();
        Role::updateOrCreate(['name' => 'super_admin'], ['display_name' => 'Platform Administrator']);
        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Administrator']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create(['company_id' => null]);
        $user->syncRoles(['super_admin']);

        return $user;
    }

    public function test_super_admin_canceling_subscription_with_stripe_id_cancels_on_stripe(): void
    {
        $company = Company::factory()->create();

        $subscription = $company->fresh('subscription')->subscription;
        $subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
            'stripe_customer_id' => 'cus_platform_cancel',
            'stripe_subscription_id' => 'sub_platform_cancel',
        ]);

        $service = Mockery::mock(StripeBillingService::class);
        $service->shouldReceive('cancelImmediately')
            ->once()
            ->andReturnUsing(function () use ($subscription) {
                $subscription->update([
                    'status' => SubscriptionStatus::CANCELED,
                    'stripe_status' => 'canceled',
                    'canceled_at' => now(),
                    'cancel_at_period_end' => false,
                ]);

                return $subscription->fresh();
            });
        $this->app->instance(StripeBillingService::class, $service);

        $admin = $this->createSuperAdmin();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson("/v1/platform/billing/companies/{$company->id}/subscription", [
            'status' => SubscriptionStatus::CANCELED->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::CANCELED->value);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::CANCELED->value,
        ]);
    }

    public function test_super_admin_canceling_subscription_without_stripe_id_only_updates_locally(): void
    {
        $company = Company::factory()->create();

        $subscription = $company->fresh('subscription')->subscription;
        $subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
            'stripe_subscription_id' => null,
        ]);

        $service = Mockery::mock(StripeBillingService::class);
        $service->shouldNotReceive('cancelImmediately');
        $this->app->instance(StripeBillingService::class, $service);

        $admin = $this->createSuperAdmin();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson("/v1/platform/billing/companies/{$company->id}/subscription", [
            'status' => SubscriptionStatus::CANCELED->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::CANCELED->value);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::CANCELED->value,
        ]);
    }

    public function test_super_admin_canceling_already_canceled_subscription_does_not_call_stripe_again(): void
    {
        $company = Company::factory()->create();

        $subscription = $company->fresh('subscription')->subscription;
        $subscription->update([
            'status' => SubscriptionStatus::CANCELED,
            'stripe_subscription_id' => 'sub_already_canceled',
            'canceled_at' => now()->subDay(),
        ]);

        $service = Mockery::mock(StripeBillingService::class);
        $service->shouldNotReceive('cancelImmediately');
        $this->app->instance(StripeBillingService::class, $service);

        $admin = $this->createSuperAdmin();
        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson("/v1/platform/billing/companies/{$company->id}/subscription", [
            'status' => SubscriptionStatus::CANCELED->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', SubscriptionStatus::CANCELED->value);
    }
}
