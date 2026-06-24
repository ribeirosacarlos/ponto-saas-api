<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\CommercialLeadStepReorderRequest;
use App\Http\Requests\Commercial\CommercialLeadStepRequest;
use App\Http\Resources\Commercial\CommercialLeadStepResource;
use App\Models\CommercialLeadStep;
use App\Services\AuditLogService;

class CommercialLeadStepController extends Controller
{
    public function __construct(protected AuditLogService $auditLogService) {}

    public function index()
    {
        $this->authorize('viewAny', CommercialLeadStep::class);

        $steps = CommercialLeadStep::query()->orderBy('position')->get();

        return CommercialLeadStepResource::collection($steps);
    }

    public function store(CommercialLeadStepRequest $request)
    {
        $this->authorize('manage', CommercialLeadStep::class);

        $step = CommercialLeadStep::create($request->validated());

        $this->auditLogService->log(
            action: 'lead_step.created',
            entityType: CommercialLeadStep::class,
            entityId: $step->id,
            description: "Etapa comercial criada: {$step->name}",
        );

        return (new CommercialLeadStepResource($step))->response()->setStatusCode(201);
    }

    public function update(CommercialLeadStepRequest $request, string $id)
    {
        $this->authorize('manage', CommercialLeadStep::class);

        $step = CommercialLeadStep::findOrFail($id);
        $step->update($request->validated());

        $this->auditLogService->log(
            action: 'lead_step.updated',
            entityType: CommercialLeadStep::class,
            entityId: $step->id,
            description: "Etapa comercial atualizada: {$step->name}",
        );

        return new CommercialLeadStepResource($step);
    }

    public function destroy(string $id)
    {
        $this->authorize('manage', CommercialLeadStep::class);

        $step = CommercialLeadStep::findOrFail($id);
        $step->delete();

        $this->auditLogService->log(
            action: 'lead_step.deleted',
            entityType: CommercialLeadStep::class,
            entityId: $step->id,
            description: "Etapa comercial removida: {$step->name}",
        );

        return response()->json(['message' => 'Etapa removida com sucesso.']);
    }

    public function reorder(CommercialLeadStepReorderRequest $request)
    {
        $this->authorize('manage', CommercialLeadStep::class);

        foreach ($request->validated()['steps'] as $stepData) {
            CommercialLeadStep::whereKey($stepData['id'])->update(['position' => $stepData['position']]);
        }

        $this->auditLogService->log(
            action: 'lead_step.reordered',
            entityType: CommercialLeadStep::class,
            description: 'Etapas comerciais reordenadas',
        );

        return CommercialLeadStepResource::collection(
            CommercialLeadStep::query()->orderBy('position')->get()
        );
    }
}
