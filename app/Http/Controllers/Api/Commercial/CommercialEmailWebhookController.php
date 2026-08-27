<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialEmailWebhookEvent;
use App\Services\Commercial\CommercialEmailWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Resend\Exceptions\WebhookSignatureVerificationException;
use Resend\WebhookSignature;

class CommercialEmailWebhookController extends Controller
{
    public function __construct(protected CommercialEmailWebhookService $webhookService) {}

    public function handle(Request $request)
    {
        $logger = Log::channel('resend_webhooks');
        $payload = $request->getContent();
        $secret = config('services.resend.webhook_secret');

        $svixId = $request->header('svix-id');
        $svixTimestamp = $request->header('svix-timestamp');
        $svixSignature = $request->header('svix-signature');

        if (! $secret || ! $svixId || ! $svixTimestamp || ! $svixSignature) {
            $logger->warning('Webhook do Resend mal configurado ou sem headers svix', [
                'secret_configured' => (bool) $secret,
                'svix_id_present' => (bool) $svixId,
            ]);

            return response()->json(['message' => 'Webhook mal configurado.'], 400);
        }

        try {
            WebhookSignature::verify($payload, [
                'svix-id' => $svixId,
                'svix-timestamp' => $svixTimestamp,
                'svix-signature' => $svixSignature,
            ], $secret, (int) config('services.resend.webhook_tolerance', 300));
        } catch (WebhookSignatureVerificationException $e) {
            $logger->warning('Webhook do Resend com assinatura inválida', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Assinatura inválida.'], 400);
        }

        $decodedPayload = json_decode($payload, true) ?? [];
        $type = $decodedPayload['type'] ?? 'unknown';

        $eventRecord = CommercialEmailWebhookEvent::query()->firstOrCreate(
            ['provider_event_id' => $svixId],
            ['type' => $type, 'payload_json' => $decodedPayload]
        );

        if ($eventRecord->processed_at) {
            return response()->json(['received' => true]);
        }

        try {
            $this->webhookService->process($type, $decodedPayload);
            $eventRecord->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            $logger->error('Falha ao processar webhook do Resend', [
                'provider_event_id' => $svixId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            $eventRecord->update(['processing_error' => $e->getMessage()]);

            return response()->json(['message' => 'Erro ao processar webhook.'], 500);
        }

        return response()->json(['received' => true]);
    }
}
