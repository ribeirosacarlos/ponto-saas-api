<?php

namespace Tests\Feature\Commercial;

use App\Mail\Commercial\CommercialSequenceMail;
use App\Models\CommercialEmailTemplate;
use App\Models\CommercialLead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendTestCommercialEmailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_rendered_template_to_given_email_with_dummy_lead_data(): void
    {
        Mail::fake();

        $template = CommercialEmailTemplate::create([
            'name' => 'Primeiro contato',
            'slug' => 'primeiro-contato',
            'subject' => 'Olá {{first_name}}',
            'body_html' => '<p>{{company_name}} conta com {{first_name}}</p>',
        ]);

        $this->artisan('commercial:emails:send-test', [
            'template' => $template->slug,
            'email' => 'destino@teste.com',
        ])->assertSuccessful();

        Mail::assertSent(CommercialSequenceMail::class, function (CommercialSequenceMail $mail) {
            return str_starts_with($mail->subjectLine, '[TESTE] Olá')
                && str_contains($mail->bodyHtml, 'Empresa de Teste');
        });
    }

    public function test_sends_using_real_lead_data_when_lead_option_given(): void
    {
        Mail::fake();

        $lead = CommercialLead::factory()->create(['contact_name' => 'Maria Souza', 'company_name' => 'Acme']);
        $template = CommercialEmailTemplate::create([
            'name' => 'Primeiro contato',
            'slug' => 'primeiro-contato',
            'subject' => 'Assunto',
            'body_html' => '<p>{{company_name}} — {{first_name}}</p>',
        ]);

        $this->artisan('commercial:emails:send-test', [
            'template' => $template->slug,
            'email' => 'destino@teste.com',
            '--lead' => $lead->id,
        ])->assertSuccessful();

        Mail::assertSent(CommercialSequenceMail::class, fn (CommercialSequenceMail $mail) => str_contains($mail->bodyHtml, 'Acme — Maria'));
    }

    public function test_fails_when_template_does_not_exist(): void
    {
        $this->artisan('commercial:emails:send-test', [
            'template' => 'nao-existe',
            'email' => 'destino@teste.com',
        ])->assertFailed();
    }
}
