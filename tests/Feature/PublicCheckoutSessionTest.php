<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Services\StripeBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Mockery;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Tests\TestCase;

class PublicCheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearPublicRateLimit();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_public_checkout_session_returns_url_when_company_and_plan_valid(): void
    {
        $plan = $this->createPlan();
        $company = Company::factory()->create();

        $session = $this->buildStripeSession();
        $service = Mockery::mock(StripeBillingService::class);
        $service->shouldReceive('createCheckoutSession')->andReturn($session)->once();
        $this->app->instance(StripeBillingService::class, $service);

        $response = $this->postJson('/v1/public/billing/checkout-session', [
            'company_id' => $company->id,
            'plan_id' => $plan->id,
        ]);

        $response->assertStatus(200)
            ->assertJson(['url' => $session->url]);
    }

    public function test_public_checkout_session_rejects_honeypot(): void
    {
        $plan = $this->createPlan();
        $company = Company::factory()->create();

        $response = $this->postJson('/v1/public/billing/checkout-session', [
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'honeypot' => 'spam value',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('honeypot');
    }

    public function test_public_checkout_session_rejects_inactive_plan(): void
    {
        $plan = $this->createPlan(['is_active' => false]);
        $company = Company::factory()->create();

        $response = $this->postJson('/v1/public/billing/checkout-session', [
            'company_id' => $company->id,
            'plan_id' => $plan->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('plan_id');
    }

    public function test_public_checkout_session_rate_limit_triggers_after_threshold(): void
    {
        $plan = $this->createPlan();
        $company = Company::factory()->create();

        $service = Mockery::mock(StripeBillingService::class);
        $service->shouldReceive('createCheckoutSession')->never();
        $this->app->instance(StripeBillingService::class, $service);

        $this->fillPublicRateLimit(10);

        $this->postJson('/v1/public/billing/checkout-session', [
            'company_id' => $company->id,
            'plan_id' => $plan->id,
        ])->assertStatus(429);
    }

    protected function buildStripeSession(string $url = 'https://checkout.stripe.com/test-session'): StripeCheckoutSession
    {
        return StripeCheckoutSession::constructFrom([
            'id' => 'cs_test',
            'url' => $url,
        ]);
    }

    protected function createPlan(array $overrides = []): Plan
    {
        return Plan::create(array_merge([
            'slug' => 'test-plan-monthly-' . Str::lower(Str::random(6)),
            'name' => 'Plano de Teste',
            'description' => 'Plano utilizado em testes.',
            'price_cents' => 1500,
            'currency' => 'EUR',
            'billing_interval' => 'month',
            'trial_days' => 0,
            'is_active' => true,
            'sort_order' => 999,
            'features' => [],
            'quotas' => [],
            'stripe_price_id' => 'price_test_session',
        ], $overrides));
    }

    protected function clearPublicRateLimit(): void
    {
        RateLimiter::clear(md5('public-billing-checkout-session'.'127.0.0.1'));
    }

    protected function fillPublicRateLimit(int $attempts): void
    {
        $key = $this->publicRateLimitKey();

        for ($i = 0; $i < $attempts; $i++) {
            RateLimiter::hit($key, 60);
        }
    }

    protected function publicRateLimitKey(): string
    {
        return md5('public-billing-checkout-session'.'127.0.0.1');
    }
}
