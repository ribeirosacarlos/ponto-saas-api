<?php

namespace App\Services\Timesheet;

use App\Models\EmployeeTimesheet;
use App\Services\TimeEntry\TimesheetCalculationService;
use Carbon\CarbonImmutable;

class TimesheetSnapshotService
{
    public function __construct(
        protected TimesheetCalculationService $calculationService,
        protected TimesheetSignatureService $signatureService
    ) {}

    public function generate(EmployeeTimesheet $timesheet, ?array $snapshot = null): void
    {
        $snapshot ??= $this->buildSnapshot($timesheet);

        // Antes de salvar o novo snapshot, verifica se havia assinaturas ativas.
        // Se houver, invalida-as (superseded) pois o documento foi alterado.
        $this->signatureService->supersedePreviousSignatures($timesheet);

        $timesheet->update([
            'snapshot' => $snapshot,
            'snapshot_generated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSnapshot(EmployeeTimesheet $timesheet): array
    {
        $closure = $timesheet->monthlyClosure;
        $employee = $timesheet->employee;

        $timezone = $employee->company?->timezone ?? config('app.timezone', 'UTC');

        $from = CarbonImmutable::create(
            $closure->reference_year,
            $closure->reference_month,
            1,
            0, 0, 0,
            $timezone
        );

        $to = $from->endOfMonth();

        $snapshot = $this->calculationService->calculateForEmployee($employee, $from, $to);

        $previousBalanceMinutes = $this->calculatePreviousBalance($timesheet);
        $currentBalanceMinutes = (int) ($snapshot['totals']['balance_minutes'] ?? 0);

        $snapshot['previous_balance_minutes'] = $previousBalanceMinutes;
        $snapshot['previous_balance_hhmm'] = $this->minutesToSignedHHMM($previousBalanceMinutes);
        $snapshot['accumulated_balance_minutes'] = $previousBalanceMinutes + $currentBalanceMinutes;
        $snapshot['accumulated_balance_hhmm'] = $this->minutesToSignedHHMM($previousBalanceMinutes + $currentBalanceMinutes);

        return $snapshot;
    }

    /**
     * Compara dois snapshots ignorando apenas os campos de horário exibido
     * (clocked_at e os horários de pareamento in/out), que são os únicos
     * afetados pelo bug de timezone corrigido em bb1b84e. Se o restante
     * (totais, resumos por dia, estrutura) for idêntico, é seguro substituir
     * o snapshot sem invalidar assinaturas já existentes.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $new
     */
    public function hasOnlyDisplayTimeChanges(array $current, array $new): bool
    {
        return $this->stripDisplayTimes($current) === $this->stripDisplayTimes($new);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function stripDisplayTimes(array $snapshot): array
    {
        if (! isset($snapshot['days']) || ! is_array($snapshot['days'])) {
            return $snapshot;
        }

        foreach ($snapshot['days'] as &$day) {
            if (isset($day['entries']) && is_array($day['entries'])) {
                foreach ($day['entries'] as &$entry) {
                    unset($entry['clocked_at']);
                }
                unset($entry);
            }

            if (isset($day['virtual_entries']) && is_array($day['virtual_entries'])) {
                foreach ($day['virtual_entries'] as &$entry) {
                    unset($entry['clocked_at']);
                }
                unset($entry);
            }

            if (isset($day['summary']['pair_details']) && is_array($day['summary']['pair_details'])) {
                foreach ($day['summary']['pair_details'] as &$pair) {
                    unset($pair['in'], $pair['out']);
                }
                unset($pair);
            }

            if (isset($day['summary']['open_pair']['in'])) {
                unset($day['summary']['open_pair']['in']);
            }
        }
        unset($day);

        return $snapshot;
    }

    private function calculatePreviousBalance(EmployeeTimesheet $timesheet): int
    {
        $closure = $timesheet->monthlyClosure;

        $previousTimesheets = EmployeeTimesheet::query()
            ->where('employee_id', $timesheet->employee_id)
            ->where('company_id', $timesheet->company_id)
            ->where('id', '!=', $timesheet->id)
            ->whereHas('monthlyClosure', function ($query) use ($closure) {
                $query->where(function ($q) use ($closure) {
                    $q->where('reference_year', '<', $closure->reference_year)
                        ->orWhere(function ($q2) use ($closure) {
                            $q2->where('reference_year', $closure->reference_year)
                                ->where('reference_month', '<', $closure->reference_month);
                        });
                });
            })
            ->get(['snapshot']);

        return $previousTimesheets->sum(function (EmployeeTimesheet $ts) {
            return (int) ($ts->snapshot['totals']['balance_minutes'] ?? 0);
        });
    }

    private function minutesToSignedHHMM(int $minutes): string
    {
        if ($minutes === 0) {
            return '00:00';
        }

        $sign = $minutes > 0 ? '+' : '-';
        $abs = abs($minutes);

        return sprintf('%s%02d:%02d', $sign, (int) floor($abs / 60), $abs % 60);
    }
}
