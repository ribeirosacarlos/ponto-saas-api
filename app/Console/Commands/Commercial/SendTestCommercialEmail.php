<?php

namespace App\Console\Commands\Commercial;

use App\Mail\Commercial\CommercialSequenceMail;
use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\CommercialEmailTemplate;
use App\Models\CommercialLead;
use App\Services\Commercial\CommercialEmailMergeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendTestCommercialEmail extends Command
{
    protected $signature = 'commercial:emails:send-test
        {template : ID ou slug do template}
        {email : E-mail de destino}
        {--lead= : ID de um lead existente para renderizar com dados reais em vez de dados fictícios}';

    protected $description = 'Renderiza um template de e-mail comercial e envia imediatamente, sem fila/agendamento/limites — só para conferir conteúdo e deliverability.';

    public function handle(CommercialEmailMergeService $mergeService): int
    {
        $template = CommercialEmailTemplate::query()
            ->where('id', $this->argument('template'))
            ->orWhere('slug', $this->argument('template'))
            ->first();

        if (! $template) {
            $this->error('Template não encontrado (busquei por id e por slug).');

            return self::FAILURE;
        }

        if ($this->option('lead')) {
            $lead = CommercialLead::find($this->option('lead'));

            if (! $lead) {
                $this->error('Lead informado em --lead não foi encontrado.');

                return self::FAILURE;
            }
        } else {
            $lead = CommercialLead::factory()->make([
                'contact_name' => 'Nome de Teste',
                'company_name' => 'Empresa de Teste',
                'email' => $this->argument('email'),
            ]);
        }

        $enrollment = new CommercialEmailSequenceEnrollment(['unsubscribe_token' => Str::random(64)]);
        $rendered = $mergeService->render($template, $lead, $enrollment);
        $unsubscribeUrl = route('public.commercial-email.unsubscribe', ['token' => $enrollment->unsubscribe_token]);

        Mail::mailer('resend')->to($this->argument('email'))->send(new CommercialSequenceMail(
            subjectLine: '[TESTE] '.$rendered['subject'],
            bodyHtml: $rendered['body_html'],
            unsubscribeUrl: $unsubscribeUrl,
        ));

        $this->info("E-mail de teste enviado para {$this->argument('email')} usando o template \"{$template->name}\".");

        return self::SUCCESS;
    }
}
