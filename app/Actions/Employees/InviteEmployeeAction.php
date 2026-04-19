<?php

namespace App\Actions\Employees;

use App\Jobs\SendEmployeeInviteJob;
use App\Models\Area;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ExtraEmployeeChargeService;
use App\Services\UserShiftService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteEmployeeAction
{
    public function __construct(
        protected UserShiftService $userShiftService,
        protected ExtraEmployeeChargeService $extraEmployeeChargeService,
        protected AuditLogService $auditLogService
    ) {}

    public function execute(User $inviter, array $data): User
    {
        if (($data['role'] ?? null) === 'employee' && $inviter->company) {
            $this->extraEmployeeChargeService->registerPendingExtraEmployees($inviter->company);
        }

        $temporaryPasswordPlain = Str::password(12);
        $inviteCodePlain = $this->generateInviteCode();

        $user = User::create([
            'company_id' => $inviter->company_id,
            'area_id' => $data['area_id'] ?? null,
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

        $this->syncManagedAreas($user, $data['managed_area_ids'] ?? []);
        $this->assignShiftFromPayload($user, $data['shift_id'] ?? null);

        $companyName = $inviter->company?->name;
        $supportEmail = config('app.support_email');

        $payload = [
            'companyName' => $companyName,
            'inviteUrl' => $this->resolveInviteUrl($user->email),
            'inviteCode' => $inviteCodePlain,
            'temporaryPassword' => $temporaryPasswordPlain,
            'supportEmail' => $supportEmail,
        ];

        SendEmployeeInviteJob::dispatch($user->id, $payload);

        $this->auditLogService->log(
            action: 'employee.invited',
            entityType: User::class,
            entityId: $user->id,
            description: 'Colaborador convidado para a empresa.',
            newValues: $this->employeeSnapshot($user),
            metadata: [
                'invite_expires_at' => optional($user->invite_expires_at)->toIso8601String(),
                'invited_by_user_id' => $inviter->id,
            ],
            companyId: $user->company_id,
        );

        return $user->load(['userShifts.shift', 'roles', 'area', 'managedAreas']);
    }

    protected function syncManagedAreas(User $user, array $managedAreaIds): void
    {
        $areaIds = Area::where('company_id', $user->company_id)
            ->whereIn('id', collect($managedAreaIds)->filter()->unique()->values())
            ->pluck('id');

        $payload = $areaIds
            ->filter()
            ->unique()
            ->mapWithKeys(fn (string $areaId) => [$areaId => [
                'id' => (string) Str::uuid(),
                'company_id' => $user->company_id,
            ]])
            ->all();

        $user->managedAreas()->sync($payload);
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

    protected function employeeSnapshot(User $user): array
    {
        $user->loadMissing(['roles', 'area', 'managedAreas', 'userShifts.shift']);

        $activeShift = $user->userShifts
            ->firstWhere('end_date', null);

        return $this->auditLogService->snapshot([
            'name' => $user->name,
            'email' => $user->email,
            'area_id' => $user->area_id,
            'area_name' => $user->area?->name,
            'role' => $user->roles->pluck('name')->first(),
            'managed_area_ids' => $user->managedAreas->pluck('id')->values()->all(),
            'managed_area_names' => $user->managedAreas->pluck('name')->values()->all(),
            'shift_id' => $activeShift?->shift_id,
            'shift_name' => $activeShift?->shift?->name,
            'invite_expires_at' => optional($user->invite_expires_at)->toIso8601String(),
        ]);
    }
}
