<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\CommercialEmailSequenceStepReorderRequest;
use App\Http\Requests\Commercial\CommercialEmailSequenceStepRequest;
use App\Http\Resources\Commercial\CommercialEmailSequenceStepResource;
use App\Models\CommercialEmailSequence;
use App\Models\CommercialEmailSequenceStep;
use App\Services\AuditLogService;

class CommercialEmailSequenceStepController extends Controller
{
    public function __construct(protected AuditLogService $auditLogService) {}

    public function store(CommercialEmailSequenceStepRequest $request, string $sequenceId)
    {
        $sequence = CommercialEmailSequence::findOrFail($sequenceId);

        $data = $request->validated();
        $data['sequence_id'] = $sequence->id;

        $step = CommercialEmailSequenceStep::create($data);

        $this->auditLogService->log(
            action: 'commercial_email_sequence_step.created',
            entityType: CommercialEmailSequence::class,
            entityId: $sequence->id,
            description: "Passo adicionado à sequência: {$sequence->name}",
        );

        return (new CommercialEmailSequenceStepResource($step->load('template')))->response()->setStatusCode(201);
    }

    public function update(CommercialEmailSequenceStepRequest $request, string $sequenceId, string $stepId)
    {
        $step = CommercialEmailSequenceStep::query()->where('sequence_id', $sequenceId)->findOrFail($stepId);
        $step->update($request->validated());

        $this->auditLogService->log(
            action: 'commercial_email_sequence_step.updated',
            entityType: CommercialEmailSequence::class,
            entityId: $sequenceId,
            description: 'Passo de sequência atualizado',
        );

        return new CommercialEmailSequenceStepResource($step->load('template'));
    }

    public function destroy(string $sequenceId, string $stepId)
    {
        $step = CommercialEmailSequenceStep::query()->where('sequence_id', $sequenceId)->findOrFail($stepId);
        $step->delete();

        $this->auditLogService->log(
            action: 'commercial_email_sequence_step.deleted',
            entityType: CommercialEmailSequence::class,
            entityId: $sequenceId,
            description: 'Passo de sequência removido',
        );

        return response()->json(['message' => 'Passo removido com sucesso.']);
    }

    public function reorder(CommercialEmailSequenceStepReorderRequest $request, string $sequenceId)
    {
        foreach ($request->validated()['steps'] as $stepData) {
            CommercialEmailSequenceStep::query()
                ->where('sequence_id', $sequenceId)
                ->whereKey($stepData['id'])
                ->update(['position' => $stepData['position']]);
        }

        $this->auditLogService->log(
            action: 'commercial_email_sequence_step.reordered',
            entityType: CommercialEmailSequence::class,
            entityId: $sequenceId,
            description: 'Passos da sequência reordenados',
        );

        return CommercialEmailSequenceStepResource::collection(
            CommercialEmailSequenceStep::query()->where('sequence_id', $sequenceId)->orderBy('position')->get()
        );
    }
}
