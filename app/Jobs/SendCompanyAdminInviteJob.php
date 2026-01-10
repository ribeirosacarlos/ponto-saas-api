<?php

namespace App\Jobs;

use App\Mail\CompanyAdminInviteMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendCompanyAdminInviteJob implements ShouldQueue
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
            Log::warning('Admin invite job: user not found', ['user_id' => $this->userId]);
            return;
        }

        $metadata = [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'email' => $user->email,
        ];

        Log::info('Starting admin invite job', $metadata);

        $inviteCode = $this->payload['inviteCode'] ?? null;

        Mail::to($user->email)->queue(new CompanyAdminInviteMail(
            userName: $user->name,
            userEmail: $user->email,
            companyName: $this->payload['companyName'] ?? $user->company?->name,
            inviteUrl: $this->resolveInviteUrl($inviteCode),
            inviteCode: $inviteCode,
            supportEmail: $this->payload['supportEmail'] ?? config('app.support_email'),
        ));

        Log::info('Admin invite email queued', $metadata);
    }

    public function failed(Throwable $exception): void
    {
        $user = User::query()->find($this->userId);

        Log::error('Failed to send admin invite email', [
            'user_id' => $this->userId,
            'company_id' => $user?->company_id,
            'email' => $user?->email,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function resolveInviteUrl(?string $code): ?string
    {
        $template = config('app.invite_url');

        if (! $template || ! $code) {
            return null;
        }

        $encodedCode = urlencode($code);

        if (str_contains($template, '{code}')) {
            return str_replace('{code}', $encodedCode, $template);
        }

        $separator = str_contains($template, '?') ? '&' : '?';

        return "{$template}{$separator}invite_code={$encodedCode}";
    }
}
