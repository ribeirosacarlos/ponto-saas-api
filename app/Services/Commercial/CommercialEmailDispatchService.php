<?php

namespace App\Services\Commercial;

use App\Jobs\Commercial\SendCommercialSequenceEmailJob;
use App\Models\CommercialEmailSend;
use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\CommercialOutreachSetting;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

class CommercialEmailDispatchService
{
    private const WEEKDAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function __construct(
        private readonly CommercialEmailMergeService $mergeService,
    ) {}

    /**
     * Núcleo de "quem recebe agora": aplica kill switch, janela de envio e
     * orçamento diário/mensal, seleciona candidatos elegíveis e enfileira os
     * envios com jitter. Retorna um resumo para log/observabilidade.
     */
    public function dispatchDue(): array
    {
        $settings = CommercialOutreachSetting::current();

        if ($settings->is_globally_paused) {
            return ['status' => 'skipped', 'reason' => 'globally_paused', 'dispatched' => 0];
        }

        if (! $this->withinSendingWindow($settings)) {
            return ['status' => 'skipped', 'reason' => 'outside_sending_window', 'dispatched' => 0];
        }

        $batchLimit = $this->remainingBudget($settings);

        if ($batchLimit <= 0) {
            return ['status' => 'skipped', 'reason' => 'budget_exhausted', 'dispatched' => 0];
        }

        $candidates = $this->selectCandidates($batchLimit);
        $dispatched = 0;

        foreach ($candidates as $index => $enrollment) {
            $emailSend = $this->createQueuedSend($enrollment);

            if (! $emailSend) {
                continue;
            }

            $delaySeconds = ($index * $settings->min_gap_seconds_between_sends) + random_int(0, 30);

            SendCommercialSequenceEmailJob::dispatch($emailSend->id)
                ->onQueue('commercial-email')
                ->delay(now()->addSeconds($delaySeconds));

            $dispatched++;
        }

        Log::channel('commercial_email')->info('Dispatch de sequência comercial executado', [
            'candidates' => $candidates->count(),
            'dispatched' => $dispatched,
            'batch_limit' => $batchLimit,
        ]);

        return ['status' => 'ok', 'dispatched' => $dispatched, 'candidates' => $candidates->count()];
    }

    private function withinSendingWindow(CommercialOutreachSetting $settings): bool
    {
        $now = Carbon::now($settings->timezone);
        $weekdayKey = self::WEEKDAY_KEYS[$now->dayOfWeek];
        $allowedDays = $settings->sending_days ?? self::WEEKDAY_KEYS;

        if (! in_array($weekdayKey, $allowedDays, true)) {
            return false;
        }

        $currentTime = $now->format('H:i');
        $startTime = substr($settings->sending_window_start_time, 0, 5);
        $endTime = substr($settings->sending_window_end_time, 0, 5);

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }

    private function remainingBudget(CommercialOutreachSetting $settings): int
    {
        $sentToday = CommercialEmailSend::query()
            ->whereDate('sent_at', Carbon::now($settings->timezone)->toDateString())
            ->where('status', '!=', CommercialEmailSend::STATUS_CANCELLED)
            ->count();

        $sentThisMonth = CommercialEmailSend::query()
            ->whereYear('sent_at', Carbon::now($settings->timezone)->year)
            ->whereMonth('sent_at', Carbon::now($settings->timezone)->month)
            ->where('status', '!=', CommercialEmailSend::STATUS_CANCELLED)
            ->count();

        $dailyRemaining = max(0, $settings->daily_send_limit - $sentToday);
        $monthlyRemaining = max(0, $settings->monthly_send_limit - $sentThisMonth);

        return min($dailyRemaining, $monthlyRemaining, $settings->max_sends_per_dispatch_run);
    }

    private function selectCandidates(int $batchLimit)
    {
        // Sobre-busca (3x) porque parte dos candidatos pode ser descartada em
        // memória por supressão de e-mail — evita subestimar o lote quando há
        // poucos leads suprimidos misturados nos primeiros resultados.
        return CommercialEmailSequenceEnrollment::query()
            ->where('status', CommercialEmailSequenceEnrollment::STATUS_ACTIVE)
            ->whereNotNull('next_send_at')
            ->where('next_send_at', '<=', now())
            ->whereDoesntHave('sends', fn ($q) => $q->where('status', CommercialEmailSend::STATUS_QUEUED))
            ->whereHas('lead', fn ($q) => $q->whereNotNull('email'))
            ->with(['lead', 'nextStep.template'])
            ->orderBy('next_send_at')
            ->limit($batchLimit * 3)
            ->get()
            ->reject(fn (CommercialEmailSequenceEnrollment $enrollment) => $enrollment->lead->isEmailSuppressed())
            ->take($batchLimit)
            ->values();
    }

    private function createQueuedSend(CommercialEmailSequenceEnrollment $enrollment): ?CommercialEmailSend
    {
        $step = $enrollment->nextStep;

        if (! $step || ! $step->template) {
            Log::channel('commercial_email')->warning('Enrollment sem próximo passo/template válido', [
                'enrollment_id' => $enrollment->id,
            ]);

            return null;
        }

        $rendered = $this->mergeService->render($step->template, $enrollment->lead, $enrollment);

        try {
            return CommercialEmailSend::create([
                'enrollment_id' => $enrollment->id,
                'lead_id' => $enrollment->lead_id,
                'sequence_step_id' => $step->id,
                'template_id' => $step->template_id,
                'idempotency_key' => "{$enrollment->id}:{$step->id}",
                'to_email' => $enrollment->lead->email,
                'rendered_subject' => $rendered['subject'],
                'rendered_body_html' => $rendered['body_html'],
                'status' => CommercialEmailSend::STATUS_QUEUED,
                'queued_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Já existe um envio agendado para este par enrollment/step — corrida
            // com outra execução do dispatcher, ignora silenciosamente.
            return null;
        }
    }
}
