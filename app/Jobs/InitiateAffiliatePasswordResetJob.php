<?php

namespace App\Jobs;

use App\Models\CommercialAffiliate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class InitiateAffiliatePasswordResetJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $email) {}

    public function handle(): void
    {
        $affiliate = CommercialAffiliate::where('email', $this->email)->first();

        if (! $affiliate) {
            return;
        }

        $code = $this->generateResetCode();

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $affiliate->email],
            ['token' => hash('sha256', $code), 'created_at' => now()],
        );

        SendAffiliatePasswordResetJob::dispatch($affiliate->id, ['resetCode' => $code]);
    }

    private function generateResetCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($index = 0; $index < 8; $index++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $code;
    }
}
