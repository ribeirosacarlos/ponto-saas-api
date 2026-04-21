<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Http\Middleware\EnsureSubscriptionTrialOrActive;
use App\Models\Company;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new PlansSeeder())->run();
        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'super_admin'], ['display_name' => 'Super Admin']);
        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_company_creation_creates_trial_subscription(): void
    {
        $now = Carbon::now();
        Carbon::setTestNow($now);

        $company = Company::factory()->create();
        $company->load('subscription.plan');

        $this->assertNotNull($company->subscription);
        $this->assertSame(SubscriptionStatus::TRIALING, $company->subscription->status);

        $trialDays = $company->subscription->plan->trial_days ?? config('billing.trial_days_default');

        $this->assertSame(
            $now->copy()->addDays($trialDays)->toDateTimeString(),
            $company->subscription->trial_ends_at->toDateTimeString()
        );
    }

    public function test_trial_expired_is_marked_past_due_by_command(): void
    {
        $company = Company::factory()->create();
        $subscription = $company->fresh('subscription')->subscription;

        $expired = $subscription->trial_ends_at->copy()->addMinute();

        Carbon::setTestNow($expired);

        Artisan::call('billing:sync-subscriptions', [
            '--company' => $company->id,
        ]);

        $this->assertSame(SubscriptionStatus::PAST_DUE, $subscription->fresh()->status);
        $this->assertTrue($subscription->fresh()->past_due_since->equalTo($expired));
    }

    public function test_past_due_within_grace_period_does_not_block(): void
    {
        $company = Company::factory()->create();
        $subscription = $company->fresh('subscription')->subscription;

        $subscription->update([
            'status' => SubscriptionStatus::PAST_DUE,
            'past_due_since' => now()->subDays(1),
            'grace_period_days' => 5,
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = app(EnsureSubscriptionTrialOrActive::class)->handle(
            $request,
            fn () => response()->json(['ok' => true], 200)
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_past_due_outside_grace_returns_402(): void
    {
        $company = Company::factory()->create();
        $subscription = $company->fresh('subscription')->subscription;

        $subscription->update([
            'status' => SubscriptionStatus::PAST_DUE,
            'past_due_since' => now()->subDays(30),
            'grace_period_days' => 7,
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = app(EnsureSubscriptionTrialOrActive::class)->handle(
            $request,
            fn () => response()->json(['ok' => true], 200)
        );

        $this->assertEquals(402, $response->getStatusCode());
    }

    public function test_ensure_plan_feature_rejects_missing_feature(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $request = Request::create('/feature', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = app(EnsurePlanFeature::class)->handle(
            $request,
            fn () => response()->json(['ok' => true], 200),
            'plan.feature:api'
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_billing_mark_past_due_command_updates_subscription(): void
    {
        $company = Company::factory()->create();

        $now = Carbon::now();
        Carbon::setTestNow($now);

        Artisan::call('billing:mark-past-due', [
            'company' => $company->id,
        ]);

        $subscription = $company->fresh('subscription')->subscription->fresh();

        $this->assertSame(SubscriptionStatus::PAST_DUE, $subscription->status);
        $this->assertEqualsWithDelta($now->timestamp, $subscription->past_due_since->timestamp, 1);
    }

    public function test_company_with_scheduled_cancellation_keeps_access_until_expiration(): void
    {
        $company = Company::factory()->create([
            'subscription_status' => SubscriptionStatus::CANCELED->value,
            'access_expires_at' => now()->addDay(),
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = app(EnsureCompanyHasAccess::class)->handle(
            $request,
            fn () => response()->json(['ok' => true], 200)
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_company_is_blocked_when_scheduled_access_expired(): void
    {
        $company = Company::factory()->create([
            'subscription_status' => SubscriptionStatus::CANCELED->value,
            'access_expires_at' => now()->subMinute(),
        ]);

        $user = User::factory()->create(['company_id' => $company->id]);
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = app(EnsureCompanyHasAccess::class)->handle(
            $request,
            fn () => response()->json(['ok' => true], 200)
        );

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertTrue($company->fresh()->is_blocked);
    }

    public function test_admin_can_schedule_subscription_cancellation(): void
    {
        $company = Company::factory()->create([
            'stripe_customer_id' => 'cus_cancel_test',
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $company->fresh('subscription')->subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
            'stripe_customer_id' => 'cus_cancel_test',
            'stripe_subscription_id' => 'sub_cancel_test',
        ]);

        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole('admin');

        $periodEnd = now()->addDays(15);

        $service = Mockery::mock(\App\Services\StripeBillingService::class);
        $service->shouldReceive('scheduleCancellationAtPeriodEnd')
            ->once()
            ->andReturn(tap($company->fresh('subscription')->subscription, function ($subscription) use ($company, $periodEnd) {
                $subscription->update([
                    'cancel_at_period_end' => true,
                    'current_period_end' => $periodEnd,
                ]);

                $company->update([
                    'access_expires_at' => $periodEnd,
                ]);
            })->fresh('company'));
        $this->app->instance(\App\Services\StripeBillingService::class, $service);

        $response = $this->actingAs($admin)->postJson('/v1/settings/subscription/cancel');

        $response->assertOk()
            ->assertJsonPath('data.cancel_at_period_end', true)
            ->assertJsonPath('data.access_expires_at', $periodEnd->toIso8601String());
    }

    public function test_non_admin_cannot_schedule_subscription_cancellation(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('employee');

        $response = $this->actingAs($user)->postJson('/v1/settings/subscription/cancel');

        $response->assertForbidden();
    }

    public function test_sync_expired_access_command_blocks_company_and_cancels_subscription(): void
    {
        $company = Company::factory()->create([
            'subscription_status' => SubscriptionStatus::ACTIVE->value,
            'access_expires_at' => now()->subMinute(),
        ]);

        $company->fresh('subscription')->subscription->update([
            'status' => SubscriptionStatus::ACTIVE,
            'cancel_at_period_end' => true,
            'current_period_end' => $company->access_expires_at,
        ]);

        Artisan::call('subscriptions:sync-expired-access', [
            '--company' => $company->id,
        ]);

        $company->refresh();

        $this->assertTrue($company->is_blocked);
        $this->assertSame(SubscriptionStatus::CANCELED->value, $company->subscription_status);
        $this->assertSame(SubscriptionStatus::CANCELED, $company->subscription->fresh()->status);
    }
}
