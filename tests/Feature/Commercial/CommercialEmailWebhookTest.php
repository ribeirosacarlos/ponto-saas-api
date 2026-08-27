<?php

namespace Tests\Feature\Commercial;

use App\Models\CommercialEmailSend;
use App\Models\CommercialEmailSequence;
use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\CommercialEmailSequenceStep;
use App\Models\CommercialEmailSuppression;
use App\Models\CommercialEmailTemplate;
use App\Models\CommercialEmailWebhookEvent;
use App\Models\CommercialLead;
use App\Models\Role;
use App\Models\User;
use App\Services\Commercial\CommercialEmailEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialEmailWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.resend.webhook_secret' => self::WEBHOOK_SECRET]);

        foreach (['super_admin', 'commercial_manager'] as $role) {
            Role::updateOrCreate(['name' => $role], ['display_name' => $role]);
        }
    }

    private function svixHeaders(string $payload): array
    {
        $id = 'msg_'.bin2hex(random_bytes(8));
        $timestamp = (string) time();

        $secret = base64_decode(substr(self::WEBHOOK_SECRET, strlen('whsec_')));
        $toSign = "{$id}.{$timestamp}.{$payload}";
        $hash = hash_hmac('sha256', $toSign, $secret);
        $signature = base64_encode(pack('H*', $hash));

        return [
            'svix-id' => $id,
            'svix-timestamp' => $timestamp,
            'svix-signature' => "v1,{$signature}",
        ];
    }

    private function emailSendWithEnrollment(): CommercialEmailSend
    {
        $manager = User::factory()->create(['company_id' => null]);
        $manager->syncRoles(['commercial_manager']);

        $sequence = CommercialEmailSequence::create(['name' => 'Cold Outbound', 'status' => 'active']);
        $template = CommercialEmailTemplate::create([
            'name' => 'Primeiro contato',
            'slug' => 'primeiro-contato',
            'subject' => 'Olá',
            'body_html' => '<p>Olá</p>',
        ]);
        $step = CommercialEmailSequenceStep::create([
            'sequence_id' => $sequence->id,
            'template_id' => $template->id,
            'position' => 1,
            'delay_days' => 0,
        ]);
        $lead = CommercialLead::factory()->create(['email' => 'lead@empresa.test']);
        $enrollment = app(CommercialEmailEnrollmentService::class)->enroll($lead, $sequence, $manager);

        return CommercialEmailSend::create([
            'enrollment_id' => $enrollment->id,
            'lead_id' => $lead->id,
            'sequence_step_id' => $step->id,
            'template_id' => $template->id,
            'idempotency_key' => "{$enrollment->id}:{$step->id}",
            'to_email' => $lead->email,
            'rendered_subject' => 'Olá',
            'rendered_body_html' => '<p>Olá</p>',
            'resend_message_id' => 're_test_123',
            'status' => CommercialEmailSend::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function test_bounced_webhook_suppresses_email_and_cancels_enrollment(): void
    {
        $emailSend = $this->emailSendWithEnrollment();

        $payload = json_encode([
            'type' => 'email.bounced',
            'data' => ['email_id' => 're_test_123'],
        ]);

        $this->postJson('/v1/commercial/email/webhook', json_decode($payload, true), $this->svixHeaders($payload))
            ->assertOk();

        $this->assertSame(CommercialEmailSend::STATUS_BOUNCED, $emailSend->fresh()->status);
        $this->assertTrue(CommercialEmailSuppression::isSuppressed('lead@empresa.test'));
        $this->assertSame(
            CommercialEmailSequenceEnrollment::STATUS_CANCELLED,
            $emailSend->fresh()->enrollment->status
        );
    }

    public function test_already_processed_webhook_event_is_not_reprocessed(): void
    {
        $emailSend = $this->emailSendWithEnrollment();

        $payload = json_encode([
            'type' => 'email.delivered',
            'data' => ['email_id' => 're_test_123'],
        ]);

        $headers = $this->svixHeaders($payload);

        // Simula que este mesmo evento (mesmo svix-id) já foi entregue e processado
        // anteriormente — o Resend pode reenviar webhooks (at-least-once delivery).
        CommercialEmailWebhookEvent::create([
            'provider_event_id' => $headers['svix-id'],
            'type' => 'email.delivered',
            'payload_json' => json_decode($payload, true),
            'processed_at' => now(),
        ]);

        $this->postJson('/v1/commercial/email/webhook', json_decode($payload, true), $headers)->assertOk();

        $this->assertSame(1, CommercialEmailWebhookEvent::query()->count());
        $this->assertNull($emailSend->fresh()->delivered_at);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $payload = json_encode(['type' => 'email.delivered', 'data' => ['email_id' => 're_test_123']]);

        $this->postJson('/v1/commercial/email/webhook', json_decode($payload, true), [
            'svix-id' => 'msg_invalid',
            'svix-timestamp' => (string) time(),
            'svix-signature' => 'v1,invalidsignature',
        ])->assertStatus(400);
    }
}
