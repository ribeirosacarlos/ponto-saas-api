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
        {--scan : Varre todos os timesheets sem assinatura ativa e recalcula o snapshot de cada um}
        {--force : Regenera mesmo havendo assinatura ativa (invalida as assinaturas existentes)}';

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
        $query = EmployeeTimesheet::query()->whereNotNull('snapshot_generated_at');

        if (! $this->option('force')) {
            $query->whereDoesntHave('activeSignatures');
        }

        $changed = 0;
        $unchanged = 0;
        $skipped = 0;

        $query->chunkById(50, function (Collection $timesheets) use (&$changed, &$unchanged, &$skipped) {
            foreach ($timesheets as $timesheet) {
                match ($this->regenerateOne($timesheet)) {
                    'changed' => $changed++,
                    'unchanged' => $unchanged++,
                    'skipped' => $skipped++,
                };
            }
        });

        $this->info("Concluído. Alterados: {$changed} | Sem alteração: {$unchanged} | Ignorados (assinatura ativa): {$skipped}");

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
     * @return 'changed'|'unchanged'|'skipped'
     */
    protected function regenerateOne(EmployeeTimesheet $timesheet): string
    {
        $timesheet->loadMissing(['employee.company', 'activeSignatures']);

        if ($timesheet->activeSignatures->isNotEmpty() && ! $this->option('force')) {
            $this->warn("Timesheet {$timesheet->id}: possui assinatura ativa, ignorado (use --force para sobrescrever).");

            return 'skipped';
        }

        if ($timesheet->activeSignatures->isNotEmpty()) {
            $this->warn("Timesheet {$timesheet->id}: regenerando com --force, assinaturas existentes serão invalidadas.");
        }

        $this->tenantManager->setTenant($timesheet->employee->company);

        $before = json_encode($timesheet->snapshot);

        $this->snapshotService->generate($timesheet);

        $after = json_encode($timesheet->refresh()->snapshot);

        if ($before === $after) {
            $this->line("Timesheet {$timesheet->id}: sem alteração.");

            return 'unchanged';
        }

        $this->info("Timesheet {$timesheet->id}: snapshot atualizado.");

        return 'changed';
    }
}
