<?php

namespace App\Services\Commercial;

use App\Models\CommercialEmailSend;
use App\Models\CommercialEmailSuppression;
use App\Models\CommercialOutreachSetting;
use Illuminate\Support\Facades\Log;

class CommercialEmailWebhookService
{
    public function __construct(
        private readonly CommercialEmailEnrollmentService $enrollmentService,
    ) {}

    public function process(string $type, array $payload): void
    {
        $messageId = $payload['data']['email_id'] ?? null;

        if (! $messageId) {
            Log::channel('resend_webhooks')->warning('Webhook do Resend sem email_id', ['type' => $type]);

            return;
        }

        $emailSend = CommercialEmailSend::query()->where('resend_message_id', $messageId)->first();

        if (! $emailSend) {
            Log::channel('resend_webhooks')->info('Webhook do Resend para envio desconhecido (ignorado)', [
                'type' => $type,
                'resend_message_id' => $messageId,
            ]);

            return;
        }

        match ($type) {
            'email.delivered' => $this->handleDelivered($emailSend),
            'email.opened' => $this->handleOpened($emailSend),
            'email.clicked' => $this->handleClicked($emailSend),
            'email.bounced' => $this->handleBounced($emailSend),
            'email.complained' => $this->handleComplained($emailSend),
            default => Log::channel('resend_webhooks')->info('Evento do Resend sem tratamento específico', ['type' => $type]),
        };
    }

    private function handleDelivered(CommercialEmailSend $emailSend): void
    {
        if (in_array($emailSend->status, [CommercialEmailSend::STATUS_BOUNCED, CommercialEmailSend::STATUS_COMPLAINED], true)) {
            return;
        }

        $emailSend->update(['status' => CommercialEmailSend::STATUS_DELIVERED, 'delivered_at' => now()]);
    }

    private function handleOpened(CommercialEmailSend $emailSend): void
    {
        if (in_array($emailSend->status, [CommercialEmailSend::STATUS_BOUNCED, CommercialEmailSend::STATUS_COMPLAINED], true)) {
            return;
        }

        $emailSend->update([
            'status' => CommercialEmailSend::STATUS_OPENED,
            'opened_at' => $emailSend->opened_at ?? now(),
        ]);
    }

    private function handleClicked(CommercialEmailSend $emailSend): void
    {
        if (in_array($emailSend->status, [CommercialEmailSend::STATUS_BOUNCED, CommercialEmailSend::STATUS_COMPLAINED], true)) {
            return;
        }

        $emailSend->update([
            'status' => CommercialEmailSend::STATUS_CLICKED,
            'first_clicked_at' => $emailSend->first_clicked_at ?? now(),
        ]);
    }

    private function handleBounced(CommercialEmailSend $emailSend): void
    {
        $emailSend->update(['status' => CommercialEmailSend::STATUS_BOUNCED, 'bounced_at' => now()]);

        $settings = CommercialOutreachSetting::current();
        $bounceCount = CommercialEmailSend::query()
            ->where('lead_id', $emailSend->lead_id)
            ->where('status', CommercialEmailSend::STATUS_BOUNCED)
            ->count();

        $reason = $bounceCount >= $settings->bounce_soft_threshold
            ? CommercialEmailSuppression::REASON_BOUNCED_SOFT_THRESHOLD
            : CommercialEmailSuppression::REASON_BOUNCED_HARD;

        $this->suppress($emailSend, $reason);
        $this->enrollmentService->cancel($emailSend->enrollment, 'bounced');
    }

    private function handleComplained(CommercialEmailSend $emailSend): void
    {
        $emailSend->update(['status' => CommercialEmailSend::STATUS_COMPLAINED, 'complained_at' => now()]);

        $this->suppress($emailSend, CommercialEmailSuppression::REASON_COMPLAINED);
        $this->enrollmentService->cancel($emailSend->enrollment, 'complained');
    }

    private function suppress(CommercialEmailSend $emailSend, string $reason): void
    {
        CommercialEmailSuppression::query()->updateOrCreate(
            ['email' => strtolower(trim($emailSend->to_email))],
            [
                'lead_id' => $emailSend->lead_id,
                'reason' => $reason,
                'source' => 'webhook',
                'suppressed_at' => now(),
            ]
        );
    }
}
