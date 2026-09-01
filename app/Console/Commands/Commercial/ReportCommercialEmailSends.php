<?php

namespace App\Console\Commands\Commercial;

use App\Models\CommercialEmailSend;
use Illuminate\Console\Command;

class ReportCommercialEmailSends extends Command
{
    protected $signature = 'commercial:emails:report
        {--email= : Filtra por e-mail do lead (busca parcial)}
        {--status= : Filtra por status (queued, sent, delivered, opened, clicked, bounced, complained, failed, cancelled)}
        {--limit=20 : Quantidade de registros, mais recentes primeiro}';

    protected $description = 'Lista o histórico de e-mails comerciais enviados/tentados — destinatário, sequência, etapa, status e datas.';

    public function handle(): int
    {
        $query = CommercialEmailSend::query()
            ->with(['template', 'sequenceStep.sequence', 'lead'])
            ->latest('created_at');

        if ($email = $this->option('email')) {
            $query->where('to_email', 'like', "%{$email}%");
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $sends = $query->limit((int) $this->option('limit'))->get();

        if ($sends->isEmpty()) {
            $this->info('Nenhum envio encontrado com esses filtros.');

            return self::SUCCESS;
        }

        $this->table(
            ['E-mail', 'Empresa', 'Sequência', 'Etapa', 'Template', 'Status', 'Enviado em', 'Entregue em', 'Aberto em'],
            $sends->map(fn (CommercialEmailSend $send) => [
                $send->to_email,
                $send->lead?->company_name,
                $send->sequenceStep?->sequence?->name,
                $send->sequenceStep?->position,
                $send->template?->name,
                $send->status,
                $send->sent_at?->format('d/m/Y H:i'),
                $send->delivered_at?->format('d/m/Y H:i'),
                $send->opened_at?->format('d/m/Y H:i'),
            ])->all()
        );

        return self::SUCCESS;
    }
}
