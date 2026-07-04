<?php

namespace App\Console\Commands;

use App\Models\TimeEntry;
use App\Services\TimeEntry\TimeEntryDayNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReconcileTimeEntryAdjustmentSequence extends Command
{
    protected $signature = 'time-entries:reconcile-adjustment-sequence
        {--company= : Restringe a reconciliacao a uma company_id especifica}
        {--from= : Data inicial (Y-m-d) do clocked_at a considerar}
        {--to= : Data final (Y-m-d) do clocked_at a considerar}
        {--apply : Persiste as correcoes. Sem essa flag roda em modo dry-run (nada e gravado)}';

    protected $description = 'Reprocessa a sequencia in/out de dias operacionais com ajustes de ponto que ficaram fora de ordem por causa do bug de janela do TimeEntryDayNormalizer (corrigido).';

    public function handle(TimeEntryDayNormalizer $normalizer): int
    {
        $apply = (bool) $this->option('apply');

        $query = TimeEntry::withoutGlobalScope('company')
            ->excludeRejected()
            ->whereNotNull('adjustment_reason');

        if ($company = $this->option('company')) {
            $query->where('company_id', $company);
        }

        if ($from = $this->option('from')) {
            $query->where('clocked_at', '>=', $from.' 00:00:00');
        }

        if ($to = $this->option('to')) {
            $query->where('clocked_at', '<=', $to.' 23:59:59');
        }

        $flagged = $query
            ->with('user.company')
            ->orderBy('clocked_at')
            ->get(['id', 'company_id', 'user_id', 'clocked_at'])
            ->filter(fn (TimeEntry $entry) => $entry->user !== null);

        $groups = $flagged->groupBy(function (TimeEntry $entry) {
            $timezone = $entry->user->company?->timezone ?: config('app.timezone', 'UTC');

            return $entry->user_id.'|'.$entry->clocked_at->clone()->setTimezone($timezone)->toDateString();
        });

        $this->info(sprintf(
            '%s ajuste(s) encontrados em %s dia(s) operacional(is) candidato(s). Modo: %s',
            $flagged->count(),
            $groups->count(),
            $apply ? 'APLICAR' : 'DRY-RUN (nada sera gravado)'
        ));

        $changedDays = 0;
        $changedEntries = 0;
        $failures = 0;

        foreach ($groups as $key => $entries) {
            [$userId, $date] = explode('|', $key, 2);
            $user = $entries->first()->user;
            $reference = $entries->first()->clocked_at->clone()->toImmutable();

            $windowStart = $entries->first()->clocked_at->clone()->subDay()->startOfDay();
            $windowEnd = $entries->first()->clocked_at->clone()->addDay()->endOfDay();

            $before = $this->snapshotDay($userId, $windowStart, $windowEnd);

            DB::beginTransaction();

            try {
                $normalizer->normalizeForReference($user, $reference);

                $after = $this->snapshotDay($userId, $windowStart, $windowEnd);
                $diff = $this->countChanges($before, $after);

                if ($diff > 0) {
                    $changedDays++;
                    $changedEntries += $diff;

                    $this->line(sprintf(
                        '[%s] company=%s user=%s date=%s: %d entrada(s) corrigida(s)%s',
                        $apply ? 'APLICADO' : 'DRY-RUN',
                        $entries->first()->company_id,
                        $userId,
                        $date,
                        $diff,
                        $apply ? '' : ' (nao persistido)'
                    ));
                }

                if ($apply) {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            } catch (Throwable $e) {
                DB::rollBack();
                $failures++;
                $this->error("Falha ao reconciliar user={$userId} date={$date}: {$e->getMessage()}");
            }
        }

        $this->info(sprintf(
            '%s: %d dia(s) com %d entrada(s) corrigida(s). Falhas: %d.',
            $apply ? 'Concluido' : 'Dry-run concluido',
            $changedDays,
            $changedEntries,
            $failures
        ));

        return self::SUCCESS;
    }

    /**
     * @return Collection<string, string>
     */
    private function snapshotDay(string $userId, $windowStart, $windowEnd): Collection
    {
        return TimeEntry::withoutGlobalScope('company')
            ->where('user_id', $userId)
            ->whereBetween('clocked_at', [$windowStart, $windowEnd])
            ->get(['id', 'type', 'event_kind'])
            ->mapWithKeys(fn (TimeEntry $entry) => [$entry->id => "{$entry->type}:{$entry->event_kind}"]);
    }

    /**
     * @param  Collection<string, string>  $before
     * @param  Collection<string, string>  $after
     */
    private function countChanges(Collection $before, Collection $after): int
    {
        return $after->filter(fn (string $value, string $id) => ($before->get($id) ?? $value) !== $value)->count();
    }
}
