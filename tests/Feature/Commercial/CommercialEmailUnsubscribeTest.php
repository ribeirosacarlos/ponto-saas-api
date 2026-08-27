<?php

namespace Tests\Feature\Commercial;

use App\Models\CommercialEmailSequence;
use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\CommercialEmailSequenceStep;
use App\Models\CommercialEmailSuppression;
use App\Models\CommercialEmailTemplate;
use App\Models\CommercialLead;
use App\Models\Role;
use App\Models\User;
use App\Services\Commercial\CommercialEmailEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialEmailUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::updateOrCreate(['name' => 'commercial_manager'], ['display_name' => 'commercial_manager']);
    }

    private function activeEnrollment(): CommercialEmailSequenceEnrollment
    {
        $manager = User::factory()->create(['company_id' => null]);
        $manager->syncRoles(['commercial_manager']);

        $sequence = CommercialEmailSequence::create(['name' => 'Cold Outbound', 'status' => 'active']);
        $template = CommercialEmailTemplate::create([
            'name' => 'Primeiro contato',
            'slug' => 'primeiro-contato',
            'subject' => 'Olá',
            'body_html' => '<p>Olá {{unsubscribe_url}}</p>',
        ]);
        CommercialEmailSequenceStep::create([
            'sequence_id' => $sequence->id,
            'template_id' => $template->id,
            'position' => 1,
            'delay_days' => 0,
        ]);
        $lead = CommercialLead::factory()->create(['email' => 'lead@empresa.test']);

        return app(CommercialEmailEnrollmentService::class)->enroll($lead, $sequence, $manager);
    }

    public function test_unsubscribe_link_cancels_enrollment_and_suppresses_email(): void
    {
        $enrollment = $this->activeEnrollment();

        $response = $this->getJson("/v1/commercial/email/unsubscribe/{$enrollment->unsubscribe_token}");

        $response->assertOk();

        $this->assertSame(CommercialEmailSequenceEnrollment::STATUS_CANCELLED, $enrollment->fresh()->status);
        $this->assertSame('unsubscribed', $enrollment->fresh()->exit_reason);
        $this->assertTrue(CommercialEmailSuppression::isSuppressed('lead@empresa.test'));
    }

    public function test_unsubscribe_with_invalid_token_returns_not_found(): void
    {
        $this->getJson('/v1/commercial/email/unsubscribe/token-invalido-inexistente')
            ->assertNotFound();
    }

    public function test_one_click_unsubscribe_post_also_works(): void
    {
        $enrollment = $this->activeEnrollment();

        $this->postJson("/v1/commercial/email/unsubscribe/{$enrollment->unsubscribe_token}")
            ->assertOk();

        $this->assertSame(CommercialEmailSequenceEnrollment::STATUS_CANCELLED, $enrollment->fresh()->status);
    }
}
