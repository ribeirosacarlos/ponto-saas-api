<?php

namespace App\Actions\Employees;

use App\Jobs\SendEmployeeInviteJob;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResendEmployeeInviteAction
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function execute(User $inviter, User $employee): User
    {
        $temporaryPasswordPlain = Str::password(12);
        $inviteCodePlain = $this->generateInviteCode();

        $employee->update([
            'password' => Hash::make($temporaryPasswordPlain),
            'invited_at' => now(),
            'invite_code_hash' => hash('sha256', $inviteCodePlain),
            'invite_expires_at' => now()->addDays(7),
            'must_change_password' => true,
        ]);

        $payload = [
            'companyName' => $inviter->company?->name,
            'inviteUrl' => $this->resolveInviteUrl($employee->email),
            'inviteCode' => $inviteCodePlain,
            'temporaryPassword' => $temporaryPasswordPlain,
            'supportEmail' => config('app.support_email'),
        ];

        SendEmployeeInviteJob::dispatch($employee->id, $payload);

        $this->auditLogService->log(
            action: 'employee.invite_resent',
            entityType: User::class,
            entityId: $employee->id,
            description: 'Convite de primeiro acesso reenviado.',
            metadata: [
                'invite_expires_at' => optional($employee->invite_expires_at)->toIso8601String(),
                'resent_by_user_id' => $inviter->id,
            ],
            companyId: $employee->company_id,
        );

        return $employee;
    }

    protected function generateInviteCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = strlen($characters);
        $inviteCode = '';

        for ($i = 0; $i < 8; $i++) {
            $inviteCode .= $characters[random_int(0, $length - 1)];
        }

        return $inviteCode;
    }

    protected function resolveInviteUrl(string $email): ?string
    {
        $template = config('app.invite_url');

        if (! $template) {
            return null;
        }

        $encodedEmail = urlencode($email);

        if (str_contains($template, '{email}')) {
            return str_replace('{email}', $encodedEmail, $template);
        }

        $separator = str_contains($template, '?') ? '&' : '?';

        return "{$template}{$separator}email={$encodedEmail}";
    }
}
