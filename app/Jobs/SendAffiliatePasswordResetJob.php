<?php

namespace App\Jobs;

use App\Mail\ResetPasswordMail;
use App\Models\CommercialAffiliate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendAffiliatePasswordResetJob implements ShouldBeEncrypted, ShouldQueue
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
            Log::warning('Affiliate password reset job: affiliate not found', ['affiliate_id' => $this->affiliateId]);

            return;
        }

        Log::info('Sending affiliate password reset email', ['affiliate_id' => $affiliate->id]);

        Mail::to($affiliate->email)->queue(new ResetPasswordMail(
            userName: $affiliate->name,
            userEmail: $affiliate->email,
            companyName: null,
            token: null,
            email: $affiliate->email,
            resetCode: $this->payload['resetCode'],
            supportEmail: config('app.support_email'),
        ));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Failed to send affiliate password reset email', [
            'affiliate_id' => $this->affiliateId,
            'error' => $exception->getMessage(),
        ]);
    }
}
