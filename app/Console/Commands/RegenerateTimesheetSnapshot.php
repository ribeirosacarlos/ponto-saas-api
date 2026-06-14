<?php

namespace App\Console\Commands;

use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Services\TenantManager;
use App\Services\Timesheet\TimesheetSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class RegenerateTimesheetSnapshot extends Command
{
    protected $signature = 'timesheets:regenerate-snapshot
        {timesheet? : ID do EmployeeTimesheet a regenerar}
        {--closure= : ID do MonthlyClosure (regenera os timesheets de todos os funcionários do fechamento)}
        {--scan : Varre todos os timesheets com snapshot gerado e recalcula o snapshot de cada um}
        {--force : Quando os totais mudariam para um timesheet já assinado, regenera mesmo assim e invalida as assinaturas existentes}';

    protected $description = 'Recalcula o snapshot de EmployeeTimesheet com a lógica corrigida de timezone do clocked_at.';

    public function __construct(
        protected TimesheetSnapshotService $snapshotService,
        protected TenantManager $tenantManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $timesheetId = $this->argument('timesheet');
        $closureId = $this->option('closure');

        if ($timesheetId) {
            return $this->regenerateCollection(
                EmployeeTimesheet::where('id', $timesheetId)->get()
            );
        }

        if ($closureId) {
            $closure = MonthlyClosure::findOrFail($closureId);

            return $this->regenerateCollection($closure->timesheets()->get());
        }

        if ($this->option('scan')) {
            return $this->scan();
        }

        $this->error('Informe um ID de timesheet, --closure=<id> ou --scan.');

        return self::FAILURE;
    }

    protected function scan(): int
    {
        $changed = 0;
        $fixedSigned = 0;
        $unchanged = 0;
        $skipped = 0;

        EmployeeTimesheet::query()
            ->whereNotNull('snapshot_generated_at')
            ->chunkById(50, function (Collection $timesheets) use (&$changed, &$fixedSigned, &$unchanged, &$skipped) {
                foreach ($timesheets as $timesheet) {
                    match ($this->regenerateOne($timesheet)) {
                        'changed' => $changed++,
                        'fixed_signed' => $fixedSigned++,
                        'unchanged' => $unchanged++,
                        'skipped' => $skipped++,
                    };
                }
            });

        $this->info("Concluído. Alterados: {$changed} | Corrigidos com assinatura preservada: {$fixedSigned} | Sem alteração: {$unchanged} | Ignorados (totais mudariam, use --force): {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, EmployeeTimesheet>  $timesheets
     */
    protected function regenerateCollection(Collection $timesheets): int
    {
        if ($timesheets->isEmpty()) {
            $this->error('Nenhum timesheet encontrado.');

            return self::FAILURE;
        }

        foreach ($timesheets as $timesheet) {
            $this->regenerateOne($timesheet);
        }

        return self::SUCCESS;
    }

    /**
     * @return 'changed'|'fixed_signed'|'unchanged'|'skipped'
     */
    protected function regenerateOne(EmployeeTimesheet $timesheet): string
    {
        // Reseta o tenant antes de carregar relações com CompanyScoped (User, EmployeeTimesheet):
        // o tenant pode ter ficado setado para a empresa do timesheet anterior em um --scan
        // que percorre múltiplas empresas, o que faria employee.company vir null aqui.
        $this->tenantManager->setTenant(null);

        try {
            $timesheet->loadMissing(['employee.company', 'activeSignatures']);

            $this->tenantManager->setTenant($timesheet->employee->company);

            $newSnapshot = $this->snapshotService->buildSnapshot($timesheet);

            if (json_encode($timesheet->snapshot) === json_encode($newSnapshot)) {
                $this->line("Timesheet {$timesheet->id}: sem alteração.");

                return 'unchanged';
            }

            if ($timesheet->activeSignatures->isNotEmpty()) {
                if ($this->snapshotService->hasOnlyDisplayTimeChanges($timesheet->snapshot, $newSnapshot)) {
                    $timesheet->update([
                        'snapshot' => $newSnapshot,
                        'snapshot_generated_at' => now(),
                    ]);

                    $this->info("Timesheet {$timesheet->id}: clocked_at corrigido, assinatura preservada.");

                    return 'fixed_signed';
                }

                if (! $this->option('force')) {
                    $this->warn("Timesheet {$timesheet->id}: possui assinatura ativa e os totais seriam alterados, ignorado (use --force para sobrescrever e invalidar a assinatura).");

                    return 'skipped';
                }

                $this->warn("Timesheet {$timesheet->id}: regenerando com --force, assinaturas existentes serão invalidadas.");
            }

            $this->snapshotService->generate($timesheet, $newSnapshot);

            $this->info("Timesheet {$timesheet->id}: snapshot atualizado.");

            return 'changed';
        } finally {
            $this->tenantManager->setTenant(null);
        }
    }
}
