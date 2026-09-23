<?php

namespace Tests\Feature\Commercial;

use App\Models\CommercialEmailSend;
use App\Models\CommercialEmailSequence;
use App\Models\CommercialEmailSequenceStep;
use App\Models\CommercialEmailTemplate;
use App\Models\CommercialLead;
use App\Services\Commercial\CommercialEmailEnrollmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialEmailReportCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function enrolledLead(): array
    {
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
        $lead = CommercialLead::factory()->create(['email' => 'lead@empresa.test', 'company_name' => 'Empresa Teste']);
        $actor = \App\Models\User::factory()->create(['company_id' => null]);
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

    public function test_report_command_lists_sends(): void
    {
        $this->enrolledLead();

        $this->artisan('commercial:emails:report')->assertSuccessful();
    }

    public function test_report_command_filters_by_email_with_no_match(): void
    {
        $this->enrolledLead();

        $this->artisan('commercial:emails:report', ['--email' => 'ninguem@nada.test'])
            ->expectsOutputToContain('Nenhum envio encontrado')
            ->assertSuccessful();
    }

    public function test_enrollments_command_lists_active_enrollment(): void
    {
        $this->enrolledLead();

        $this->artisan('commercial:emails:enrollments', ['--status' => 'active'])->assertSuccessful();
    }

    public function test_enrollments_command_filters_by_email_with_no_match(): void
    {
        $this->enrolledLead();

        $this->artisan('commercial:emails:enrollments', ['--email' => 'ninguem@nada.test'])
            ->expectsOutputToContain('Nenhuma inscrição encontrada')
            ->assertSuccessful();
    }
}
