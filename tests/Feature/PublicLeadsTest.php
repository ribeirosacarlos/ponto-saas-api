<?php

namespace Tests\Feature;

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PublicLeadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearLeadsRateLimit();
        $this->clearOptOutRateLimit();
    }

    public function test_store_creates_lead_with_valid_payload(): void
    {
        $response = $this->postJson('/v1/public/leads', [
            'email' => 'Lead@Example.com',
            'lead_magnet_type' => 'planilha',
            'page_slug' => 'registro-horario-obligatorio-espana',
            'consented_at' => now()->toIso8601String(),
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);

        $lead = Lead::sole();
        $this->assertSame('lead@example.com', $lead->email);
        $this->assertSame('planilha', $lead->lead_magnet_type);
        $this->assertSame('registro-horario-obligatorio-espana', $lead->page_slug);
        $this->assertNotNull($lead->consented_at);
        $this->assertNotNull($lead->ip_hash);
    }

    public function test_store_validates_email(): void
    {
        $response = $this->postJson('/v1/public/leads', [
            'email' => 'not-an-email',
            'lead_magnet_type' => 'planilha',
            'consented_at' => now()->toIso8601String(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_store_requires_consent(): void
    {
        $response = $this->postJson('/v1/public/leads', [
            'email' => 'lead@example.com',
            'lead_magnet_type' => 'planilha',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('consented_at');
    }

    public function test_store_validates_lead_magnet_type(): void
    {
        $response = $this->postJson('/v1/public/leads', [
            'email' => 'lead@example.com',
            'lead_magnet_type' => 'invalido',
            'consented_at' => now()->toIso8601String(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('lead_magnet_type');
    }

    public function test_store_rejects_honeypot(): void
    {
        $response = $this->postJson('/v1/public/leads', [
            'email' => 'lead@example.com',
            'lead_magnet_type' => 'planilha',
            'consented_at' => now()->toIso8601String(),
            'honeypot' => 'spam',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('honeypot');
        $this->assertSame(0, Lead::count());
    }

    public function test_store_is_silent_for_duplicate_email_and_magnet(): void
    {
        $email = 'lead@example.com';

        Lead::create([
            'email' => $email,
            'lead_magnet_type' => 'planilha',
            'consented_at' => now(),
        ]);

        $response = $this->postJson('/v1/public/leads', [
            'email' => $email,
            'lead_magnet_type' => 'planilha',
            'consented_at' => now()->toIso8601String(),
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame(1, Lead::count());
    }

    public function test_store_allows_same_email_for_different_magnet_type(): void
    {
        $email = 'lead@example.com';

        Lead::create([
            'email' => $email,
            'lead_magnet_type' => 'planilha',
            'consented_at' => now(),
        ]);

        $response = $this->postJson('/v1/public/leads', [
            'email' => $email,
            'lead_magnet_type' => 'guia',
            'consented_at' => now()->toIso8601String(),
        ]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame(2, Lead::count());
    }

    public function test_store_rate_limit_triggers_after_threshold(): void
    {
        $this->fillLeadsRateLimit(3);

        $this->postJson('/v1/public/leads', [
            'email' => 'lead@example.com',
            'lead_magnet_type' => 'planilha',
            'consented_at' => now()->toIso8601String(),
        ])->assertStatus(429);

        $this->assertSame(0, Lead::count());
    }

    public function test_optout_deletes_all_leads_for_email(): void
    {
        $email = 'lead@example.com';

        Lead::create([
            'email' => $email,
            'lead_magnet_type' => 'planilha',
            'consented_at' => now(),
        ]);
        Lead::create([
            'email' => $email,
            'lead_magnet_type' => 'guia',
            'consented_at' => now(),
        ]);

        $response = $this->postJson('/v1/public/leads/optout', ['email' => $email]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame(0, Lead::where('email', $email)->count());
    }

    public function test_optout_validates_email(): void
    {
        $response = $this->postJson('/v1/public/leads/optout', ['email' => 'not-an-email']);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    protected function clearLeadsRateLimit(): void
    {
        RateLimiter::clear(md5('public-leads'.'127.0.0.1'));
    }

    protected function clearOptOutRateLimit(): void
    {
        RateLimiter::clear(md5('public-leads-optout'.'127.0.0.1'));
    }

    protected function fillLeadsRateLimit(int $attempts): void
    {
        $key = md5('public-leads'.'127.0.0.1');

        for ($i = 0; $i < $attempts; $i++) {
            RateLimiter::hit($key, 3600);
        }
    }
}
