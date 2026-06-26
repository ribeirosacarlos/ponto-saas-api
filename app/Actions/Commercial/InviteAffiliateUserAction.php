<?php

namespace App\Actions\Commercial;

use App\Jobs\SendAffiliateInviteJob;
use App\Models\CommercialAffiliate;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class InviteAffiliateUserAction
{
    public function execute(CommercialAffiliate $affiliate): User
    {
        $inviteCodePlain = $this->generateInviteCode();

        $user = User::create([
            'name' => $affiliate->name,
            'email' => $affiliate->email,
            'password' => Hash::make(Str::random(32)),
            'company_id' => null,
            'invited_at' => now(),
            'invite_code_hash' => hash('sha256', $inviteCodePlain),
            'invite_expires_at' => now()->addDays(7),
            'must_change_password' => true,
        ]);

        $user->assignRole('affiliate');

        $affiliate->forceFill(['user_id' => $user->id])->save();

        SendAffiliateInviteJob::dispatch($affiliate->id, [
            'inviteUrl' => $this->resolveInviteUrl($affiliate->email),
            'inviteCode' => $inviteCodePlain,
        ]);

        return $user;
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
        $template = config('app.invite_url');

        if (! $template) {
            return null;
        }

        $encoded = urlencode($email);

        return str_contains($template, '{email}')
            ? str_replace('{email}', $encoded, $template)
            : $template.(str_contains($template, '?') ? '&' : '?')."email={$encoded}";
    }
}
