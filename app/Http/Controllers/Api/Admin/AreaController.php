<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAreaRequest;
use App\Http\Requests\UpdateAreaRequest;
use App\Models\Area;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request)
    {
        $query = Area::where('company_id', $request->user()->company_id)
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->paginate((int) min(max($request->integer('per_page', 20), 1), 100));
    }

    public function store(StoreAreaRequest $request)
    {
        $area = Area::create([
            'company_id' => $request->user()->company_id,
            'name' => $request->input('name'),
        ]);

        $this->auditLogService->log(
            action: 'area.created',
            entityType: Area::class,
            entityId: $area->id,
            description: 'Área criada.',
            newValues: $this->auditLogService->snapshot($area, ['name']),
            companyId: $area->company_id,
        );

        return response()->json($area, 201);
    }

    public function show(Request $request, Area $area)
    {
        $this->authorizeCompany($request, $area);

        return $area;
    }

    public function update(UpdateAreaRequest $request, Area $area)
    {
        $this->authorizeCompany($request, $area);
        $before = $this->auditLogService->snapshot($area, ['name']);

        $area->update($request->validated());

        [$oldValues, $newValues] = $this->auditLogService->diff($before, $this->auditLogService->snapshot($area->fresh(), ['name']));

        if ($oldValues !== [] || $newValues !== []) {
            $this->auditLogService->log(
                action: 'area.updated',
                entityType: Area::class,
                entityId: $area->id,
                description: 'Área atualizada.',
                oldValues: $oldValues,
                newValues: $newValues,
                companyId: $area->company_id,
            );
        }

        return $area->fresh();
    }

    public function destroy(Request $request, Area $area)
    {
        $this->authorizeCompany($request, $area);
        $snapshot = $this->auditLogService->snapshot($area, ['name']);

        $area->delete();

        $this->auditLogService->log(
            action: 'area.deleted',
            entityType: Area::class,
            entityId: $area->id,
            description: 'Área removida.',
            oldValues: $snapshot,
            companyId: $area->company_id,
        );

        return response()->json(['message' => 'Área removida.']);
    }

    protected function authorizeCompany(Request $request, Area $area): void
    {
        if ((string) $area->company_id !== (string) $request->user()->company_id) {
            abort(403, 'Área não pertence à empresa atual.');
        }
    }
}
