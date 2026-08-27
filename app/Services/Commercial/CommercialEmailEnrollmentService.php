<?php

namespace App\Services\Commercial;

use App\Models\CommercialEmailSequence;
use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\CommercialEmailSequenceStep;
use App\Models\CommercialLead;
use App\Models\CommercialOutreachSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommercialEmailEnrollmentService
{
    private const WEEKDAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function enroll(CommercialLead $lead, CommercialEmailSequence $sequence, User $actor): CommercialEmailSequenceEnrollment
    {
        if (! $lead->email) {
            throw ValidationException::withMessages([
                'lead_id' => 'O lead não possui e-mail cadastrado.',
            ]);
        }

        if ($lead->isEmailSuppressed()) {
            throw ValidationException::withMessages([
                'lead_id' => 'Este e-mail está na lista de supressão e não pode receber novos envios.',
            ]);
        }

        if ($sequence->status !== 'active') {
            throw ValidationException::withMessages([
                'sequence_id' => 'Só é possível inscrever leads em sequências ativas.',
            ]);
        }

        $firstStep = $sequence->firstActiveStep();

        if (! $firstStep) {
            throw ValidationException::withMessages([
                'sequence_id' => 'Esta sequência não possui etapas ativas.',
            ]);
        }

        $hasActiveOrPaused = CommercialEmailSequenceEnrollment::query()
            ->where('lead_id', $lead->id)
            ->where('sequence_id', $sequence->id)
            ->whereIn('status', [CommercialEmailSequenceEnrollment::STATUS_ACTIVE, CommercialEmailSequenceEnrollment::STATUS_PAUSED])
            ->exists();

        if ($hasActiveOrPaused) {
            throw ValidationException::withMessages([
                'lead_id' => 'Este lead já possui uma inscrição ativa ou pausada nesta sequência.',
            ]);
        }

        $enrolledAt = now();

        try {
            return CommercialEmailSequenceEnrollment::create([
                'lead_id' => $lead->id,
                'sequence_id' => $sequence->id,
                'status' => CommercialEmailSequenceEnrollment::STATUS_ACTIVE,
                'next_step_id' => $firstStep->id,
                'next_send_at' => $this->nextSendAtForStep($firstStep, $enrolledAt),
                'enrolled_at' => $enrolledAt,
                'enrolled_by_user_id' => $actor->id,
                'unsubscribe_token' => Str::random(64),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'lead_id' => 'Este lead já possui uma inscrição ativa ou pausada nesta sequência.',
            ]);
        }
    }

    public function pause(CommercialEmailSequenceEnrollment $enrollment, User $actor, ?string $reason = null): void
    {
        if (! $enrollment->isActive()) {
            throw ValidationException::withMessages([
                'enrollment' => 'Só é possível pausar uma inscrição ativa.',
            ]);
        }

        $enrollment->update([
            'status' => CommercialEmailSequenceEnrollment::STATUS_PAUSED,
            'paused_at' => now(),
            'paused_by_user_id' => $actor->id,
            'pause_reason' => $reason,
        ]);
    }

    public function resume(CommercialEmailSequenceEnrollment $enrollment): void
    {
        if ($enrollment->status !== CommercialEmailSequenceEnrollment::STATUS_PAUSED) {
            throw ValidationException::withMessages([
                'enrollment' => 'Só é possível retomar uma inscrição pausada.',
            ]);
        }

        $step = $enrollment->nextStep;

        $enrollment->update([
            'status' => CommercialEmailSequenceEnrollment::STATUS_ACTIVE,
            'paused_at' => null,
            'paused_by_user_id' => null,
            'pause_reason' => null,
            'next_send_at' => $step ? $this->nextSendAtForStep($step, now()) : null,
        ]);
    }

    public function cancel(CommercialEmailSequenceEnrollment $enrollment, string $exitReason): void
    {
        if (in_array($enrollment->status, [CommercialEmailSequenceEnrollment::STATUS_COMPLETED, CommercialEmailSequenceEnrollment::STATUS_CANCELLED], true)) {
            return;
        }

        $enrollment->update([
            'status' => CommercialEmailSequenceEnrollment::STATUS_CANCELLED,
            'exit_reason' => $exitReason,
            'cancelled_at' => now(),
            'next_send_at' => null,
        ]);
    }

    public function markReplied(CommercialEmailSequenceEnrollment $enrollment, User $actor): void
    {
        if (in_array($enrollment->status, [CommercialEmailSequenceEnrollment::STATUS_COMPLETED, CommercialEmailSequenceEnrollment::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'enrollment' => 'Esta inscrição já foi encerrada.',
            ]);
        }

        $enrollment->update([
            'status' => CommercialEmailSequenceEnrollment::STATUS_CANCELLED,
            'exit_reason' => 'replied',
            'replied_at' => now(),
            'replied_marked_by_user_id' => $actor->id,
            'cancelled_at' => now(),
            'next_send_at' => null,
        ]);
    }

    /**
     * Move a inscrição para depois de um envio bem-sucedido do passo indicado:
     * avança para o próximo passo ativo, ou conclui a sequência se era o último.
     */
    public function advanceAfterSend(CommercialEmailSequenceEnrollment $enrollment, CommercialEmailSequenceStep $sentStep): void
    {
        $next = $sentStep->nextActive();

        if (! $next) {
            $enrollment->update([
                'current_step_id' => $sentStep->id,
                'next_step_id' => null,
                'next_send_at' => null,
                'status' => CommercialEmailSequenceEnrollment::STATUS_COMPLETED,
                'exit_reason' => 'sequence_finished',
                'completed_at' => now(),
            ]);

            return;
        }

        $enrollment->update([
            'current_step_id' => $sentStep->id,
            'next_step_id' => $next->id,
            'next_send_at' => $this->nextSendAtForStep($next, now()),
        ]);
    }

    /**
     * Calcula o próximo horário de envio de um passo a partir de uma data-base
     * (inscrição ou "agora"), ajustando para cair dentro da janela/dias de envio
     * configurados em outreach_settings.
     */
    public function nextSendAtForStep(CommercialEmailSequenceStep $step, Carbon|\DateTimeInterface $from): Carbon
    {
        $settings = CommercialOutreachSetting::current();

        $target = Carbon::parse($from)
            ->setTimezone($settings->timezone)
            ->addDays($step->delay_days);

        if ($step->send_time_override) {
            [$hour, $minute] = explode(':', $step->send_time_override);
            $target->setTime((int) $hour, (int) $minute, 0);
        }

        return $this->adjustToSendingWindow($target, $settings);
    }

    private function adjustToSendingWindow(Carbon $target, CommercialOutreachSetting $settings): Carbon
    {
        $allowedDays = $settings->sending_days ?? self::WEEKDAY_KEYS;
        $startTime = substr($settings->sending_window_start_time, 0, 5);
        $endTime = substr($settings->sending_window_end_time, 0, 5);

        for ($i = 0; $i < 14; $i++) {
            $weekdayKey = self::WEEKDAY_KEYS[$target->dayOfWeek];

            if (! in_array($weekdayKey, $allowedDays, true)) {
                $target = $target->copy()->addDay()->setTimeFromTimeString($startTime.':00');

                continue;
            }

            $currentTime = $target->format('H:i');

            if ($currentTime < $startTime) {
                $target->setTimeFromTimeString($startTime.':00');

                return $target;
            }

            if ($currentTime > $endTime) {
                $target = $target->copy()->addDay()->setTimeFromTimeString($startTime.':00');

                continue;
            }

            return $target;
        }

        return $target;
    }
}
