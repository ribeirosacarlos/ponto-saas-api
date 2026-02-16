<?php

namespace App\Jobs;

use App\Mail\EmployeeInviteMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendEmployeeInviteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public array $backoff = [60, 300, 900, 1800, 3600];
    public int $timeout = 60;

    public function __construct(
        public string $userId,
        public array $payload,
        public ?string $recipientEmail = null,
    ) {}

    public function handle(): void
    {
        $user = User::query()
            ->with('company')
            ->find($this->userId);

        if (! $user) {
            Log::warning('Invite job: user not found', ['user_id' => $this->userId]);
            return;
        }

        $metadata = [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'email' => $this->recipientEmail ?? $user->email,
        ];

        Log::info('Starting employee invite job', $metadata);

        $targetEmail = $this->recipientEmail ?? $user->email;

        Mail::to($targetEmail)->queue(new EmployeeInviteMail(
            userName: $user->name,
            userEmail: $user->email,
            companyName: $this->payload['companyName'] ?? $user->company?->name,
            inviteUrl: $this->payload['inviteUrl'] ?? null,
            inviteCode: $this->payload['inviteCode'] ?? null,
            temporaryPassword: $this->payload['temporaryPassword'] ?? null,
            supportEmail: $this->payload['supportEmail'] ?? config('app.support_email'),
        ));

        Log::info('Employee invite email queued', $metadata);
    }

    public function failed(Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        Log::error('Failed to send employee invite email', [
            'user_id' => $this->userId,
            'company_id' => $user?->company_id,
            'email' => $user?->email,
            'error' => $exception->getMessage(),
        ]);
    }
}
