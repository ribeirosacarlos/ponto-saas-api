<?php

namespace Tests\Feature\Commercial;

use App\Jobs\Commercial\SendCommercialSequenceEmailJob;
use App\Models\CommercialEmailSend;
use App\Models\CommercialEmailSequence;
use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\CommercialEmailSequenceStep;
use App\Models\CommercialEmailTemplate;
use App\Models\CommercialLead;
use App\Models\CommercialOutreachSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\Commercial\CommercialEmailEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommercialEmailDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['super_admin', 'commercial_manager', 'commercial_agent'] as $role) {
            Role::updateOrCreate(['name' => $role], ['display_name' => $role]);
        }
    }

    private function enrolledLeadDueNow(): CommercialEmailSequenceEnrollment
    {
        $manager = User::factory()->create(['company_id' => null]);
        $manager->syncRoles(['commercial_manager']);

        $sequence = CommercialEmailSequence::create(['name' => 'Cold Outbound', 'status' => 'active']);
        $template = CommercialEmailTemplate::create([
            'name' => 'Primeiro contato',
            'slug' => 'primeiro-contato',
            'subject' => 'Olá {{contact_name}}',
            'body_html' => '<p>Olá {{first_name}}</p>',
        ]);
        $step = CommercialEmailSequenceStep::create([
            'sequence_id' => $sequence->id,
            'template_id' => $template->id,
            'position' => 1,
            'delay_days' => 0,
        ]);
        $lead = CommercialLead::factory()->create(['email' => 'lead@empresa.test']);

        $enrollment = app(CommercialEmailEnrollmentService::class)->enroll($lead, $sequence, $manager);

        // Força o envio a estar "vencido" agora, independentemente da janela de envio calculada.
        $enrollment->update(['next_send_at' => now()->subMinute()]);

        return $enrollment->fresh();
    }

    public function test_dispatch_command_queues_job_for_due_enrollment(): void
    {
        Queue::fake();
        $this->enrolledLeadDueNow();

        $this->artisan('commercial:emails:dispatch-due')->assertSuccessful();

        Queue::assertPushed(SendCommercialSequenceEmailJob::class, 1);
        $this->assertSame(1, CommercialEmailSend::query()->where('status', CommercialEmailSend::STATUS_QUEUED)->count());
    }

    public function test_running_dispatch_twice_does_not_duplicate_queued_send(): void
    {
        Queue::fake();
        $this->enrolledLeadDueNow();

        $this->artisan('commercial:emails:dispatch-due');
        $this->artisan('commercial:emails:dispatch-due');

        Queue::assertPushed(SendCommercialSequenceEmailJob::class, 1);
        $this->assertSame(1, CommercialEmailSend::query()->count());
    }

    public function test_global_kill_switch_prevents_dispatch(): void
    {
        Queue::fake();
        $this->enrolledLeadDueNow();

        CommercialOutreachSetting::current()->update(['is_globally_paused' => true]);

        $this->artisan('commercial:emails:dispatch-due');

        Queue::assertNotPushed(SendCommercialSequenceEmailJob::class);
        $this->assertSame(0, CommercialEmailSend::query()->count());
    }

    public function test_daily_limit_prevents_dispatch_when_exhausted(): void
    {
        Queue::fake();
        $enrollment = $this->enrolledLeadDueNow();

        CommercialOutreachSetting::current()->update(['daily_send_limit' => 1]);

        CommercialEmailSend::create([
            'enrollment_id' => $enrollment->id,
            'lead_id' => $enrollment->lead_id,
            'sequence_step_id' => $enrollment->next_step_id,
            'template_id' => $enrollment->nextStep->template_id,
            'idempotency_key' => 'already-sent-today',
            'to_email' => 'outro@empresa.test',
            'rendered_subject' => 'Assunto',
            'rendered_body_html' => '<p>Corpo</p>',
            'status' => CommercialEmailSend::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $this->artisan('commercial:emails:dispatch-due');

        Queue::assertNotPushed(SendCommercialSequenceEmailJob::class);
    }
}
