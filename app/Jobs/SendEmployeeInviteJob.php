<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmployeeInviteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $userId,
        public string $inviteCodePlain,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            Log::warning('Invite job: user not found', ['user_id' => $this->userId]);
            return;
        }

        // Log invite data so future channels (email/whatsapp/sms) can use it without exposing real channels.
        Log::info('Employee invite generated', [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'email' => $user->email,
            'invite_code' => $this->inviteCodePlain,
        ]);
    }
}
