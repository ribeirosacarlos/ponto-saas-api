<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeStoreRequest;
use App\Models\Shift;
use App\Models\User;
use App\Actions\Employees\InviteEmployeeAction;
use App\Services\UserShiftService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

    public function store(EmployeeStoreRequest $request, InviteEmployeeAction $inviteEmployeeAction)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $user = $inviteEmployeeAction->execute($request->user(), $data);

        return response()->json($user, 201);
    }

    public function show($id)
    {
        $employee = User::findOrFail($id);
        $this->authorize('view', $employee);

        return $employee->load(['userShifts.shift', 'roles']);
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

        return $employee->load(['userShifts.shift', 'roles']);
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

}
