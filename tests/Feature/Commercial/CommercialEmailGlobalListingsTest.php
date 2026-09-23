<?php

namespace Tests\Feature\Commercial;

use App\Models\CommercialEmailSend;
use App\Models\CommercialEmailSequence;
use App\Models\CommercialEmailSequenceStep;
use App\Models\CommercialEmailTemplate;
use App\Models\CommercialLead;
use App\Models\Role;
use App\Models\User;
use App\Services\Commercial\CommercialEmailEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialEmailGlobalListingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'commercial_manager', 'commercial_agent'] as $role) {
            Role::updateOrCreate(['name' => $role], ['display_name' => $role]);
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['company_id' => null]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function enrolledLead(?User $assignedAgent = null): array
    {
        $sequence = CommercialEmailSequence::create(['name' => 'Cold Outbound', 'status' => 'active']);
        $template = CommercialEmailTemplate::create([
            'name' => 'Primeiro contato',
            'slug' => 'primeiro-contato-'.uniqid(),
            'subject' => 'Olá',
            'body_html' => '<p>Olá</p>',
        ]);
        $step = CommercialEmailSequenceStep::create([
            'sequence_id' => $sequence->id,
            'template_id' => $template->id,
            'position' => 1,
            'delay_days' => 0,
        ]);
        $lead = CommercialLead::factory()->create([
            'email' => 'lead-'.uniqid().'@empresa.test',
            'assigned_to_user_id' => $assignedAgent?->id,
        ]);
        $actor = User::factory()->create(['company_id' => null]);
        $enrollment = app(CommercialEmailEnrollmentService::class)->enroll($lead, $sequence, $actor);

        $send = CommercialEmailSend::create([
            'enrollment_id' => $enrollment->id,
            'lead_id' => $lead->id,
            'sequence_step_id' => $step->id,
            'template_id' => $template->id,
            'idempotency_key' => "{$enrollment->id}:{$step->id}",
            'to_email' => $lead->email,
            'rendered_subject' => 'Olá',
            'rendered_body_html' => '<p>Olá</p>',
            'status' => CommercialEmailSend::STATUS_DELIVERED,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        return compact('lead', 'enrollment', 'send');
    }

    public function test_manager_can_list_all_enrollments(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $this->enrolledLead();

        $response = $this->actingAs($manager)->getJson('/v1/admin/commercial/email/enrollments');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.lead.company_name', fn ($v) => is_string($v));
    }

    public function test_manager_can_list_all_sends(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $this->enrolledLead();

        $response = $this->actingAs($manager)->getJson('/v1/admin/commercial/email/sends');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'delivered');
    }

    public function test_sends_can_be_filtered_by_status(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $this->enrolledLead();

        $response = $this->actingAs($manager)->getJson('/v1/admin/commercial/email/sends?status=bounced');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    public function test_commercial_agent_only_sees_own_leads_in_enrollments_listing(): void
    {
        $agent = $this->userWithRole('commercial_agent');
        $otherAgent = $this->userWithRole('commercial_agent');

        $this->enrolledLead($agent);
        $this->enrolledLead($otherAgent);

        $this->actingAs($agent)
            ->getJson('/v1/admin/commercial/email/enrollments')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_commercial_agent_only_sees_own_leads_in_sends_listing(): void
    {
        $agent = $this->userWithRole('commercial_agent');
        $otherAgent = $this->userWithRole('commercial_agent');

        $this->enrolledLead($agent);
        $this->enrolledLead($otherAgent);

        $this->actingAs($agent)
            ->getJson('/v1/admin/commercial/email/sends')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
