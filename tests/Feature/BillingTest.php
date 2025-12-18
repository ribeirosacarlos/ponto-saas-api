<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureSubscriptionTrialOrActive;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        (new PlansSeeder())->run();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

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
        $subscription = $company->subscription;

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
        $subscription = $company->subscription;

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
        $subscription = $company->subscription;

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

        $subscription = $company->subscription->fresh();

        $this->assertSame(SubscriptionStatus::PAST_DUE, $subscription->status);
        $this->assertEqualsWithDelta($now->timestamp, $subscription->past_due_since->timestamp, 1);
    }
}
