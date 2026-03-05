<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Models\StripeWebhookEvent;
use App\Services\StripeBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(protected StripeBillingService $stripeBillingService)
    {
    }

    public function handle(Request $request)
    {
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');
        $payload = $request->getContent();

        Log::info('Stripe webhook received', [
            'signature_present' => (bool) $signature,
            'payload' => json_decode($payload, true),
        ]);

        if (! $signature || ! $secret) {
            Log::warning('Stripe webhook configurações ausentes', [
                'signature' => (bool) $signature,
                'secret_configured' => (bool) $secret,
            ]);

            return response()->json(['message' => 'Webhook mal configurado.'], 400);
        }

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $secret);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook assinatura inválida', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Assinatura inválida.'], 400);
        } catch (\UnexpectedValueException $e) {
            Log::warning('Stripe webhook payload inválido', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Payload inválido.'], 400);
        }

        $decodedPayload = json_decode($payload, true) ?? [];

        $eventRecord = StripeWebhookEvent::firstOrCreate(
            ['event_id' => $event->id],
            ['type' => $event->type, 'payload_json' => $decodedPayload]
        );

        if ($eventRecord->processed_at) {
            return response()->json(['received' => true]);
        }

        $eventRecord->payload_json = $decodedPayload;
        $eventRecord->type = $event->type;

        Log::debug('Stripe webhook processing', [
            'event_id' => $event->id,
            'type' => $event->type,
            'record_id' => $eventRecord->id,
        ]);

        try {
            $this->stripeBillingService->processEvent($event);
            $eventRecord->processed_at = now();
            $eventRecord->save();
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processamento falhou', [
                'event_id' => $event->id,
                'type' => $event->type,
                'error' => $e->getMessage(),
                'exception' => $e,
                'payload' => $decodedPayload,
                'record_id' => $eventRecord->id,
            ]);

            return response()->json(['message' => 'Erro ao processar webhook.'], 500);
        }

        return response()->json(['received' => true]);
    }
}
