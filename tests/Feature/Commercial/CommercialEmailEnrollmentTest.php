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

class CommercialEmailEnrollmentTest extends TestCase
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

    private function activeSequenceWithStep(): CommercialEmailSequence
    {
        $sequence = CommercialEmailSequence::create(['name' => 'Cold Outbound', 'status' => 'active']);
        $template = CommercialEmailTemplate::create([
            'name' => 'Primeiro contato',
            'slug' => 'primeiro-contato',
            'subject' => 'Olá {{contact_name}}',
            'body_html' => '<p>Olá {{first_name}} da {{company_name}}</p>',
        ]);

        CommercialEmailSequenceStep::create([
            'sequence_id' => $sequence->id,
            'template_id' => $template->id,
            'position' => 1,
            'delay_days' => 0,
        ]);

        return $sequence;
    }

    public function test_lead_can_be_enrolled_in_active_sequence(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $sequence = $this->activeSequenceWithStep();
        $lead = CommercialLead::factory()->create(['email' => 'lead@empresa.test']);

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/email/enrollments', [
            'sequence_id' => $sequence->id,
            'lead_ids' => [$lead->id],
        ]);

        $response->assertStatus(207);
        $response->assertJsonPath('meta.enrolled', 1);

        $this->assertDatabaseHas(
            (new CommercialEmailSequenceEnrollment)->getTable(),
            ['lead_id' => $lead->id, 'sequence_id' => $sequence->id, 'status' => 'active']
        );
    }

    public function test_lead_without_email_cannot_be_enrolled(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $sequence = $this->activeSequenceWithStep();
        $lead = CommercialLead::factory()->create(['email' => null]);

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/email/enrollments', [
            'sequence_id' => $sequence->id,
            'lead_ids' => [$lead->id],
        ]);

        $response->assertStatus(207);
        $response->assertJsonPath('meta.enrolled', 0);
        $response->assertJsonPath('data.0.status', 'error');
    }

    public function test_suppressed_email_cannot_be_enrolled(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $sequence = $this->activeSequenceWithStep();
        $lead = CommercialLead::factory()->create(['email' => 'blocked@empresa.test']);

        CommercialEmailSuppression::create([
            'email' => 'blocked@empresa.test',
            'reason' => CommercialEmailSuppression::REASON_UNSUBSCRIBED,
            'source' => 'manual',
            'suppressed_at' => now(),
        ]);

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/email/enrollments', [
            'sequence_id' => $sequence->id,
            'lead_ids' => [$lead->id],
        ]);

        $response->assertJsonPath('meta.enrolled', 0);
    }

    public function test_lead_cannot_be_enrolled_twice_in_the_same_sequence(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $sequence = $this->activeSequenceWithStep();
        $lead = CommercialLead::factory()->create(['email' => 'lead@empresa.test']);

        app(CommercialEmailEnrollmentService::class)->enroll($lead, $sequence, $manager);

        $response = $this->actingAs($manager)->postJson('/v1/admin/commercial/email/enrollments', [
            'sequence_id' => $sequence->id,
            'lead_ids' => [$lead->id],
        ]);

        $response->assertJsonPath('meta.enrolled', 0);
        $this->assertSame(1, CommercialEmailSequenceEnrollment::query()->where('lead_id', $lead->id)->count());
    }

    public function test_commercial_agent_cannot_enroll_lead_assigned_to_another_agent(): void
    {
        $agent = $this->userWithRole('commercial_agent');
        $otherAgent = $this->userWithRole('commercial_agent');
        $sequence = $this->activeSequenceWithStep();
        $lead = CommercialLead::factory()->create(['email' => 'lead@empresa.test', 'assigned_to_user_id' => $otherAgent->id]);

        $response = $this->actingAs($agent)->postJson('/v1/admin/commercial/email/enrollments', [
            'sequence_id' => $sequence->id,
            'lead_ids' => [$lead->id],
        ]);

        $response->assertJsonPath('meta.enrolled', 0);
    }

    public function test_enrollment_can_be_paused(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $sequence = $this->activeSequenceWithStep();
        $lead = CommercialLead::factory()->create(['email' => 'lead@empresa.test']);
        $enrollment = app(CommercialEmailEnrollmentService::class)->enroll($lead, $sequence, $manager);

        $response = $this->actingAs($manager)
            ->postJson("/v1/admin/commercial/email/enrollments/{$enrollment->id}/pause");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'paused');
    }

    public function test_paused_enrollment_can_be_resumed(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $sequence = $this->activeSequenceWithStep();
        $lead = CommercialLead::factory()->create(['email' => 'lead@empresa.test']);
        $enrollmentService = app(CommercialEmailEnrollmentService::class);
        $enrollment = $enrollmentService->enroll($lead, $sequence, $manager);
        $enrollmentService->pause($enrollment, $manager);

        $response = $this->actingAs($manager)
            ->postJson("/v1/admin/commercial/email/enrollments/{$enrollment->id}/resume");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
    }

    public function test_marking_enrollment_as_replied_cancels_it(): void
    {
        $manager = $this->userWithRole('commercial_manager');
        $sequence = $this->activeSequenceWithStep();
        $lead = CommercialLead::factory()->create(['email' => 'lead@empresa.test']);
        $enrollment = app(CommercialEmailEnrollmentService::class)->enroll($lead, $sequence, $manager);

        $response = $this->actingAs($manager)->postJson("/v1/admin/commercial/email/enrollments/{$enrollment->id}/mark-replied");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
        $response->assertJsonPath('data.exit_reason', 'replied');
    }
}
