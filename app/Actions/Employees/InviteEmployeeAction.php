<?php

namespace App\Actions\Employees;

use App\Jobs\SendEmployeeInviteJob;
use App\Models\Shift;
use App\Models\User;
use App\Services\UserShiftService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteEmployeeAction
{
    public function __construct(
        protected UserShiftService $userShiftService
    ) {}

    public function execute(User $inviter, array $data): User
    {
        $temporaryPasswordPlain = Str::password(12);
        $inviteCodePlain = $this->generateInviteCode();

        $user = User::create([
            'company_id' => $inviter->company_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($temporaryPasswordPlain),
            'invited_at' => now(),
            'invite_code_hash' => hash('sha256', $inviteCodePlain),
            'invite_expires_at' => now()->addDays(7),
            'must_change_password' => true,
        ]);

        if (! empty($data['role'])) {
            $user->assignRole($data['role']);
        }

        $this->assignShiftFromPayload($user, $data['shift_id'] ?? null);

        $companyName = $inviter->company?->name;
        $supportEmail = $inviter->company?->email ?? config('mail.from.address');

        $payload = [
            'companyName' => $companyName,
            'inviteUrl' => $this->resolveInviteUrl($inviteCodePlain),
            'inviteCode' => $inviteCodePlain,
            'temporaryPassword' => $temporaryPasswordPlain,
            'supportEmail' => $supportEmail,
        ];

        SendEmployeeInviteJob::dispatch($user->id, $payload);

        return $user->load(['userShifts.shift']);
    }

    protected function assignShiftFromPayload(User $user, ?string $shiftId): void
    {
        if ($shiftId) {
            $shift = $this->resolveShift($user->company_id, $shiftId);
            $this->userShiftService->assign($user, $shift);
            return;
        }

        $this->userShiftService->assignDefaultIfAvailable($user);
    }

    protected function resolveShift(?string $companyId, string $shiftId): Shift
    {
        $shift = Shift::where('company_id', $companyId)
            ->where('id', $shiftId)
            ->first();

        if (! $shift) {
            throw ValidationException::withMessages([
                'shift_id' => 'Jornada inexistente para esta empresa.',
            ]);
        }

        return $shift;
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

    protected function resolveInviteUrl(string $code): ?string
    {
        $template = config('app.invite_url');

        if (! $template) {
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
