<?php

namespace App\Jobs\Commercial;

use App\Mail\Commercial\CommercialSequenceMail;
use App\Models\CommercialEmailSend;
use App\Models\CommercialEmailSequenceEnrollment;
use App\Services\AuditLogService;
use App\Services\Commercial\CommercialEmailEnrollmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCommercialSequenceEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 1800, 3600];

    public int $timeout = 60;

    public function __construct(public string $emailSendId) {}

    public function handle(CommercialEmailEnrollmentService $enrollmentService, AuditLogService $auditLogService): void
    {
        $emailSend = CommercialEmailSend::find($this->emailSendId);

        if (! $emailSend || $emailSend->status !== CommercialEmailSend::STATUS_QUEUED) {
            return;
        }

        $enrollment = $emailSend->enrollment;

        if (! $enrollment || ! $enrollment->isActive()) {
            $emailSend->update([
                'status' => CommercialEmailSend::STATUS_CANCELLED,
                'cancel_reason' => 'enrollment_no_longer_active',
                'cancelled_at' => now(),
            ]);

            return;
        }

        if ($enrollment->lead->isEmailSuppressed()) {
            $emailSend->update([
                'status' => CommercialEmailSend::STATUS_CANCELLED,
                'cancel_reason' => 'email_suppressed',
                'cancelled_at' => now(),
            ]);

            $enrollmentService->cancel($enrollment, 'unsubscribed');

            return;
        }

        $emailSend->increment('attempt_count');

        $mailable = new CommercialSequenceMail(
            subjectLine: $emailSend->rendered_subject,
            bodyHtml: $emailSend->rendered_body_html,
            unsubscribeUrl: route('public.commercial-email.unsubscribe', ['token' => $enrollment->unsubscribe_token]),
        );

        $sentMessage = Mail::mailer('resend')->to($emailSend->to_email)->send($mailable);

        $resendMessageId = $this->extractResendMessageId($sentMessage);

        $emailSend->update([
            'status' => CommercialEmailSend::STATUS_SENT,
            'sent_at' => now(),
            'resend_message_id' => $resendMessageId,
        ]);

        $enrollmentService->advanceAfterSend($enrollment, $emailSend->sequenceStep);

        $auditLogService->log(
            action: 'commercial_email.sent',
            entityType: CommercialEmailSequenceEnrollment::class,
            entityId: $enrollment->id,
            description: "E-mail comercial enviado para {$emailSend->to_email}",
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::channel('commercial_email')->error('Falha ao enviar e-mail de sequência comercial', [
            'email_send_id' => $this->emailSendId,
            'error' => $exception->getMessage(),
        ]);

        $emailSend = CommercialEmailSend::find($this->emailSendId);

        if (! $emailSend) {
            return;
        }

        $emailSend->update([
            'status' => CommercialEmailSend::STATUS_FAILED,
            'failure_reason' => $exception->getMessage(),
            'failed_at' => now(),
        ]);

        $emailSend->enrollment?->update([
            'status' => CommercialEmailSequenceEnrollment::STATUS_PAUSED,
            'paused_at' => now(),
            'pause_reason' => 'send_failed_permanently',
        ]);
    }

    private function extractResendMessageId(mixed $sentMessage): ?string
    {
        try {
            $header = $sentMessage?->getOriginalMessage()?->getHeaders()->get('X-Resend-Email-ID');

            return $header?->getBodyAsString();
        } catch (Throwable) {
            return null;
        }
    }
}
