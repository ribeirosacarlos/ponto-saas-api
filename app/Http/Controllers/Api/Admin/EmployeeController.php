<?php

namespace App\Http\Controllers\Api\Admin;

use App\Jobs\SendEmployeeInviteJob;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeStoreRequest;
use App\Models\Shift;
use App\Models\User;
use App\Services\UserShiftService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function __construct(
        protected UserShiftService $userShiftService
    ) {
    }

    public function index(Request $request)
    {
        return User::where('company_id', $request->user()->company_id)->paginate(20);
    }

    public function store(EmployeeStoreRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        $temporaryPasswordPlain = Str::password(12);
        $inviteCodePlain = $this->generateInviteCode();
        $inviteCodeHash = hash('sha256', $inviteCodePlain);

        $user = User::create([
            'company_id' => $request->user()->company_id,
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => Hash::make($temporaryPasswordPlain),
            'invited_at' => now(),
            'invite_code_hash' => $inviteCodeHash,
            'invite_expires_at' => now()->addDays(7),
            'must_change_password' => true,
        ]);

        if (! empty($data['role'])) {
            $user->assignRole($data['role']);
        }

        $this->assignShiftFromRequest($user, $data['shift_id'] ?? null);

        SendEmployeeInviteJob::dispatch($user->id, $inviteCodePlain);

        return response()->json($user->load(['userShifts.shift']), 201);
    }

    public function show($id)
    {
        $employee = User::findOrFail($id);
        $this->authorize('view', $employee);

        return $employee->load(['userShifts.shift']);
    }

    public function update($id, EmployeeStoreRequest $request)
    {
        $employee = User::findOrFail($id);
        $this->authorize('update', $employee);

        $data = $request->validated();

        $changes = collect([
            'name'  => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ])->filter(fn ($value) => ! is_null($value))->toArray();

        if (! empty($changes)) {
            $employee->update($changes);
        }

        if (! empty($data['password'])) {
            $employee->update(['password' => Hash::make($data['password'])]);
        }

        if (! empty($data['role'])) {
            $employee->syncRoles([$data['role']]);
        }

        if (array_key_exists('shift_id', $data) && $data['shift_id']) {
            $shift = $this->resolveShift($request->user()->company_id, $data['shift_id']);
            $this->userShiftService->assign($employee, $shift);
        }

        return $employee->load(['userShifts.shift']);
    }

    public function destroy($id)
    {
        $employee = User::findOrFail($id);
        $this->authorize('delete', $employee);

        $employee->delete();

        return response()->json(['message' => 'Deletado']);
    }

    public function assignShift(Request $request, User $employee)
    {
        $this->authorize('update', $employee);

        if ($employee->company_id !== $request->user()->company_id) {
            abort(403, 'Funcionário não pertence à empresa atual.');
        }

        $data = $request->validate([
            'shift_id'   => 'required|uuid|exists:shifts,id',
            'start_date' => 'nullable|date',
        ]);

        $shift = $this->resolveShift($request->user()->company_id, $data['shift_id']);
        $startDate = ! empty($data['start_date']) ? Carbon::parse($data['start_date']) : null;

        $assignment = $this->userShiftService->assign($employee, $shift, $startDate);

        return $assignment->load('shift');
    }

    protected function assignShiftFromRequest(User $user, ?string $shiftId): void
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
        $shift = Shift::where('company_id', $companyId)->where('id', $shiftId)->first();

        if (! $shift) {
            throw ValidationException::withMessages([
                'shift_id' => 'Jornada inexistente para esta empresa.',
            ]);
        }

        return $shift;
    }

    private function generateInviteCode(): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = strlen($characters);
        $inviteCode = '';

        for ($i = 0; $i < 8; $i++) {
            $inviteCode .= $characters[random_int(0, $length - 1)];
        }

        return $inviteCode;
    }
}
