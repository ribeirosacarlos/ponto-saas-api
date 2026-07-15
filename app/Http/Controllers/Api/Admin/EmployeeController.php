<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Employees\InviteEmployeeAction;
use App\Actions\Employees\ResendEmployeeInviteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployeeStoreRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ExtraEmployeeChargeService;
use App\Services\UserShiftService;
use App\Services\UserVisibilityService;
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
        protected ExtraEmployeeChargeService $extraEmployeeChargeService,
        protected AuditLogService $auditLogService
    ) {}

    public function index(Request $request)
    {
        $query = $this->userVisibilityService->visibleUsersQuery($request->user());

        if ($request->input('status') === 'inactive') {
            $query->onlyTrashed();
        }

        $employees = $query
            ->with(['roles', 'area', 'managedAreas'])
            ->paginate($request->integer('per_page', 20));

        $employees->through(fn (User $employee) => (new EmployeeResource($employee))->resolve($request));

        return $employees;
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

        return response()->json((new EmployeeResource($user->load(['roles', 'area', 'managedAreas'])))->resolve($request), 201);
    }

    public function show($id)
    {
        $employee = User::where('company_id', request()->user()->company_id)->findOrFail($id);
        $this->authorize('view', $employee);

        return response()->json((new EmployeeResource(
            $employee->load(['userShifts.shift', 'roles', 'area', 'managedAreas'])
        ))->resolve(request()));
    }

    public function update($id, EmployeeStoreRequest $request)
    {
        $employee = User::where('company_id', $request->user()->company_id)->findOrFail($id);
        $this->authorize('update', $employee);
        $before = $this->employeeSnapshot($employee);

        $data = $request->validated();
        $areas = $this->resolveAreas($request->user()->company_id, $data);

        $changes = collect([
            'name' => $data['name'] ?? null,
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
            $employee->tokens()->delete();
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

        $employee = $employee->fresh(['userShifts.shift', 'roles', 'area', 'managedAreas']);
        [$oldValues, $newValues] = $this->auditLogService->diff($before, $this->employeeSnapshot($employee));

        if ($oldValues !== [] || $newValues !== []) {
            $this->auditLogService->log(
                action: 'employee.updated',
                entityType: User::class,
                entityId: $employee->id,
                description: 'Dados do colaborador atualizados.',
                oldValues: $oldValues,
                newValues: $newValues,
                companyId: $employee->company_id,
            );
        }

        return response()->json((new EmployeeResource($employee))->resolve($request));
    }

    public function destroy($id)
    {
        $employee = User::where('company_id', request()->user()->company_id)->findOrFail($id);
        $this->authorize('delete', $employee);
        $snapshot = $this->employeeSnapshot($employee);

        $employee->delete();

        $this->auditLogService->log(
            action: 'employee.deleted',
            entityType: User::class,
            entityId: $employee->id,
            description: 'Colaborador removido.',
            oldValues: $snapshot,
            companyId: $employee->company_id,
        );

        return response()->json(['message' => 'Deletado']);
    }

    public function restore($id)
    {
        $employee = User::withTrashed()->where('company_id', request()->user()->company_id)->findOrFail($id);
        $this->authorize('restore', $employee);

        if (! $employee->trashed()) {
            return response()->json(['message' => 'Colaborador não está desativado.'], 422);
        }

        $employee->restore();

        if ($previousRole = $this->resolvePreviousRole($employee)) {
            $employee->assignRole($previousRole);
        }

        $employee = $employee->fresh(['roles', 'area', 'managedAreas']);

        $this->auditLogService->log(
            action: 'employee.restored',
            entityType: User::class,
            entityId: $employee->id,
            description: 'Colaborador reativado.',
            newValues: $this->employeeSnapshot($employee),
            companyId: $employee->company_id,
        );

        return response()->json((new EmployeeResource($employee))->resolve(request()));
    }

    protected function resolvePreviousRole(User $employee): ?string
    {
        $lastDeletion = AuditLog::where('entity_type', User::class)
            ->where('entity_id', $employee->id)
            ->where('action', 'employee.deleted')
            ->orderByDesc('created_at')
            ->first();

        return $lastDeletion?->old_values['role'] ?? null;
    }

    public function resendInvite(Request $request, $id, ResendEmployeeInviteAction $action)
    {
        $employee = User::where('company_id', $request->user()->company_id)->findOrFail($id);
        $this->authorize('update', $employee);

        $action->execute($request->user(), $employee);

        return response()->json(['message' => 'Convite reenviado com sucesso.']);
    }

    public function assignShift(Request $request, User $employee)
    {
        $this->authorize('update', $employee);

        if ($employee->company_id !== $request->user()->company_id) {
            abort(403, 'Funcionário não pertence à empresa atual.');
        }

        $data = $request->validate([
            'shift_id' => 'required|uuid|exists:shifts,id',
            'start_date' => 'nullable|date',
        ]);
        $before = $this->employeeShiftSnapshot($employee);

        $shift = $this->resolveShift($request->user()->company_id, $data['shift_id']);
        $startDate = ! empty($data['start_date']) ? Carbon::parse($data['start_date']) : null;

        $assignment = $this->userShiftService->assign($employee, $shift, $startDate);

        $this->auditLogService->log(
            action: 'employee.shift_assigned',
            entityType: User::class,
            entityId: $employee->id,
            description: 'Jornada do colaborador atribuída.',
            oldValues: $before,
            newValues: $this->employeeShiftSnapshot($employee->fresh('userShifts.shift')),
            metadata: [
                'assignment_id' => $assignment->id,
                'start_date' => $assignment->start_date,
            ],
            companyId: $employee->company_id,
        );

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

    protected function employeeSnapshot(User $user): array
    {
        $user->loadMissing(['roles', 'area', 'managedAreas', 'userShifts.shift']);

        return $this->auditLogService->snapshot([
            'name' => $user->name,
            'email' => $user->email,
            'area_id' => $user->area_id,
            'area_name' => $user->area?->name,
            'role' => $user->roles->pluck('name')->first(),
            'managed_area_ids' => $user->managedAreas->pluck('id')->values()->all(),
            'managed_area_names' => $user->managedAreas->pluck('name')->values()->all(),
            ...$this->employeeShiftSnapshot($user),
        ]);
    }

    protected function employeeShiftSnapshot(User $user): array
    {
        $user->loadMissing(['userShifts.shift']);

        $activeShift = $user->userShifts
            ->sortByDesc('start_date')
            ->first(fn ($shift) => $shift->end_date === null);

        return $this->auditLogService->snapshot([
            'shift_id' => $activeShift?->shift_id,
            'shift_name' => $activeShift?->shift?->name,
            'shift_start_date' => $activeShift?->start_date,
            'shift_end_date' => $activeShift?->end_date,
        ]);
    }
}
