<?php

namespace App\Jobs;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPasswordResetEmailJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 1800, 3600];

    public int $timeout = 60;

    public function __construct(
        public string $userId,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $user = User::query()
            ->with('company')
            ->find($this->userId);

        if (! $user) {
            Log::warning('Password reset job: user not found', ['user_id' => $this->userId]);

            return;
        }

        $metadata = ['user_id' => $this->userId];

        Log::info('Starting password reset email job', $metadata);

        Mail::to($user->email)->queue(new ResetPasswordMail(
            userName: $this->payload['userName'] ?? $user->name,
            userEmail: $this->payload['userEmail'] ?? $user->email,
            companyName: $this->payload['companyName'] ?? $user->company?->name,
            token: $this->payload['token'] ?? null,
            email: $this->payload['email'] ?? $user->email,
            resetCode: $this->payload['resetCode'] ?? null,
            supportEmail: $this->payload['supportEmail'] ?? config('app.support_email'),
        ));

        Log::info('Password reset email queued', $metadata);
    }

    public function failed(Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        Log::error('Failed to send password reset email', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
