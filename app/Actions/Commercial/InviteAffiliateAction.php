<?php

namespace App\Actions\Commercial;

use App\Jobs\SendAffiliateInviteJob;
use App\Models\CommercialAffiliate;
use App\Services\AuditLogService;

class InviteAffiliateAction
{
    public function __construct(protected AuditLogService $auditLogService) {}

    public function execute(array $data, bool $sendEmail = true): CommercialAffiliate
    {
        $inviteCodePlain = $this->generateInviteCode();

        $affiliate = CommercialAffiliate::create([
            ...$data,
            'password'         => null,
            'invite_code_hash' => hash('sha256', $inviteCodePlain),
            'invite_expires_at' => now()->addDays(7),
        ]);

        if ($sendEmail) {
            SendAffiliateInviteJob::dispatch($affiliate->id, [
                'inviteUrl'  => $this->resolveInviteUrl($affiliate->email),
                'inviteCode' => $inviteCodePlain,
            ]);
        }

        $this->auditLogService->log(
            action: 'affiliate.invited',
            entityType: CommercialAffiliate::class,
            entityId: $affiliate->id,
            description: "Convite enviado para afiliado: {$affiliate->name}",
        );

        return $affiliate;
    }

    public function resend(CommercialAffiliate $affiliate): void
    {
        $inviteCodePlain = $this->generateInviteCode();

        $affiliate->forceFill([
            'invite_code_hash' => hash('sha256', $inviteCodePlain),
            'invite_expires_at' => now()->addDays(7),
        ])->save();

        SendAffiliateInviteJob::dispatch($affiliate->id, [
            'inviteUrl'  => $this->resolveInviteUrl($affiliate->email),
            'inviteCode' => $inviteCodePlain,
        ]);
    }

    private function generateInviteCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $len = strlen($chars);
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, $len - 1)];
        }

        return $code;
    }

    private function resolveInviteUrl(string $email): ?string
    {
        $template = config('app.affiliate_invite_url');

        if (! $template) {
            return null;
        }

        $encoded = urlencode($email);

        if (str_contains($template, '{email}')) {
            return str_replace('{email}', $encoded, $template);
        }

        $sep = str_contains($template, '?') ? '&' : '?';

        return "{$template}{$sep}email={$encoded}";
    }
}
