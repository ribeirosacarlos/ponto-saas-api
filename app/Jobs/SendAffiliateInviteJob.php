<?php

namespace App\Jobs;

use App\Mail\AffiliateInviteMail;
use App\Models\CommercialAffiliate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAffiliateInviteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [60, 300, 900, 1800, 3600];
    public int $timeout = 60;

    public function __construct(
        public string $affiliateId,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $affiliate = CommercialAffiliate::find($this->affiliateId);

        if (! $affiliate) {
            Log::warning('Affiliate invite job: affiliate not found', ['affiliate_id' => $this->affiliateId]);
            return;
        }

        Log::info('Sending affiliate invite email', ['affiliate_id' => $affiliate->id, 'email' => $affiliate->email]);

        Mail::to($affiliate->email)->queue(new AffiliateInviteMail(
            affiliateName: $affiliate->name,
            inviteUrl: $this->payload['inviteUrl'] ?? null,
            inviteCode: $this->payload['inviteCode'],
            supportEmail: config('app.support_email'),
        ));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Failed to send affiliate invite email', [
            'affiliate_id' => $this->affiliateId,
            'error' => $exception->getMessage(),
        ]);
    }
}
