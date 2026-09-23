<?php

namespace App\Console\Commands\Commercial;

use App\Models\CommercialEmailSequenceEnrollment;
use Illuminate\Console\Command;

class ReportCommercialEmailEnrollments extends Command
{
    protected $signature = 'commercial:emails:enrollments
        {--email= : Filtra por e-mail do lead (busca parcial)}
        {--status= : Filtra por status (active, paused, completed, cancelled)}
        {--limit=20 : Quantidade de registros, mais recentes primeiro}';

    protected $description = 'Lista as inscrições de leads em sequências de e-mail comercial — status atual, etapa e próximo envio.';

    public function handle(): int
    {
        $query = CommercialEmailSequenceEnrollment::query()
            ->with(['lead', 'sequence', 'currentStep', 'nextStep'])
            ->latest('enrolled_at');

        if ($email = $this->option('email')) {
            $query->whereHas('lead', fn ($q) => $q->where('email', 'like', "%{$email}%"));
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $enrollments = $query->limit((int) $this->option('limit'))->get();

        if ($enrollments->isEmpty()) {
            $this->info('Nenhuma inscrição encontrada com esses filtros.');

            return self::SUCCESS;
        }

        $this->table(
            ['E-mail', 'Empresa', 'Sequência', 'Status', 'Motivo saída', 'Etapa atual', 'Próxima etapa', 'Próximo envio'],
            $enrollments->map(fn (CommercialEmailSequenceEnrollment $enrollment) => [
                $enrollment->lead?->email,
                $enrollment->lead?->company_name,
                $enrollment->sequence?->name,
                $enrollment->status,
                $enrollment->exit_reason,
                $enrollment->currentStep?->name ?? $enrollment->currentStep?->position,
                $enrollment->nextStep?->name ?? $enrollment->nextStep?->position,
                $enrollment->next_send_at?->format('d/m/Y H:i'),
            ])->all()
        );

        return self::SUCCESS;
    }
}
