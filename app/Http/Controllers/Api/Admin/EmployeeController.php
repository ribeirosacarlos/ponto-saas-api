<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeStoreRequest;
use App\Models\Area;
use App\Models\Shift;
use App\Models\User;
use App\Actions\Employees\InviteEmployeeAction;
use App\Services\ExtraEmployeeChargeService;
use App\Services\UserVisibilityService;
use App\Services\UserShiftService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmployeeController extends Controller
{
    public function __construct(
        protected UserVisibilityService $userVisibilityService,
        protected UserShiftService $userShiftService,
        protected ExtraEmployeeChargeService $extraEmployeeChargeService
    ) {
    }

    public function index(Request $request)
    {
        return $this->userVisibilityService
            ->visibleUsersQuery($request->user())
            ->with(['roles', 'area', 'managedAreas'])
            ->paginate(20);
    }

    public function store(EmployeeStoreRequest $request, InviteEmployeeAction $inviteEmployeeAction)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $areas = $this->resolveAreas($request->user()->company_id, $data);
        $data['managed_area_ids'] = $areas['managed_areas']->pluck('id')->values()->all();

        if (array_key_exists('area_id', $data) && ! $areas['primary_area']) {
            $data['area_id'] = null;
        }

        $user = $inviteEmployeeAction->execute($request->user(), $data);

        return response()->json($user, 201);
    }

    public function show($id)
    {
        $employee = User::where('company_id', request()->user()->company_id)->findOrFail($id);
        $this->authorize('view', $employee);

        return $employee->load(['userShifts.shift', 'roles', 'area', 'managedAreas']);
    }

    public function update($id, EmployeeStoreRequest $request)
    {
        $employee = User::where('company_id', $request->user()->company_id)->findOrFail($id);
        $this->authorize('update', $employee);

        $data = $request->validated();
        $areas = $this->resolveAreas($request->user()->company_id, $data);

        $changes = collect([
            'name'  => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ])->filter(fn ($value) => ! is_null($value))->toArray();

        if (array_key_exists('area_id', $data)) {
            $changes['area_id'] = $data['area_id'];
        }

        if (! empty($changes)) {
            $employee->update($changes);
        }

        if (! empty($data['password'])) {
            $employee->update(['password' => Hash::make($data['password'])]);
        }

        if (! empty($data['role'])) {
            $currentlyEmployee = $employee->hasRole('employee');
            $willBeEmployee = $data['role'] === 'employee';

            if (! $currentlyEmployee && $willBeEmployee) {
                $this->extraEmployeeChargeService->registerPendingExtraEmployees($employee->company);
            }

            $employee->syncRoles([$data['role']]);

            if (! in_array($data['role'], ['manager', 'area_manager'], true) && ! array_key_exists('managed_area_ids', $data)) {
                $this->syncManagedAreas($employee, collect());
            }
        }

        if (array_key_exists('shift_id', $data) && $data['shift_id']) {
            $shift = $this->resolveShift($request->user()->company_id, $data['shift_id']);
            $this->userShiftService->assign($employee, $shift);
        }

        $currentRoleName = $employee->fresh('roles')->roles->pluck('name')->first();

        if (array_key_exists('managed_area_ids', $data) || ! in_array($currentRoleName, ['manager', 'area_manager'], true)) {
            $managedAreas = in_array($currentRoleName, ['manager', 'area_manager'], true)
                ? $areas['managed_areas']
                : collect();

            $this->syncManagedAreas($employee, $managedAreas);
        }

        return $employee->load(['userShifts.shift', 'roles', 'area', 'managedAreas']);
    }

    public function destroy($id)
    {
        $employee = User::where('company_id', request()->user()->company_id)->findOrFail($id);
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

    protected function resolveAreas(string $companyId, array $data): array
    {
        $areaIds = collect(array_merge(
            array_filter([$data['area_id'] ?? null]),
            $data['managed_area_ids'] ?? [],
        ))
            ->filter()
            ->unique()
            ->values();

        if ($areaIds->isEmpty()) {
            return [
                'primary_area' => null,
                'managed_areas' => collect(),
            ];
        }

        $areas = Area::where('company_id', $companyId)
            ->whereIn('id', $areaIds)
            ->get()
            ->keyBy('id');

        if ($areas->count() !== $areaIds->count()) {
            throw ValidationException::withMessages([
                'area_id' => 'Uma ou mais áreas não pertencem à empresa atual.',
            ]);
        }

        return [
            'primary_area' => isset($data['area_id']) ? $areas->get($data['area_id']) : null,
            'managed_areas' => collect($data['managed_area_ids'] ?? [])
                ->map(fn (string $areaId) => $areas->get($areaId))
                ->filter(),
        ];
    }

    protected function syncManagedAreas(User $user, Collection $areas): void
    {
        $payload = $areas
            ->keyBy('id')
            ->map(fn (Area $area) => [
                'id' => (string) Str::uuid(),
                'company_id' => $user->company_id,
            ])
            ->all();

        $user->managedAreas()->sync($payload);
    }

}
