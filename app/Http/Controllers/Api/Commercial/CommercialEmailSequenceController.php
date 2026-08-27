<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\CommercialEmailSequenceRequest;
use App\Http\Resources\Commercial\CommercialEmailSequenceResource;
use App\Models\CommercialEmailSequence;
use App\Services\AuditLogService;

class CommercialEmailSequenceController extends Controller
{
    public function __construct(protected AuditLogService $auditLogService) {}

    public function index()
    {
        $sequences = CommercialEmailSequence::query()
            ->with('steps.template')
            ->orderByDesc('created_at')
            ->get();

        return CommercialEmailSequenceResource::collection($sequences);
    }

    public function show(string $id)
    {
        $sequence = CommercialEmailSequence::query()->with('steps.template')->findOrFail($id);

        return new CommercialEmailSequenceResource($sequence);
    }

    public function store(CommercialEmailSequenceRequest $request)
    {
        $data = $request->validated();
        $data['created_by_user_id'] = $request->user()->id;

        $sequence = CommercialEmailSequence::create($data);

        $this->auditLogService->log(
            action: 'commercial_email_sequence.created',
            entityType: CommercialEmailSequence::class,
            entityId: $sequence->id,
            description: "Sequência de e-mail criada: {$sequence->name}",
        );

        return (new CommercialEmailSequenceResource($sequence))->response()->setStatusCode(201);
    }

    public function update(CommercialEmailSequenceRequest $request, string $id)
    {
        $sequence = CommercialEmailSequence::findOrFail($id);
        $sequence->update($request->validated());

        $this->auditLogService->log(
            action: 'commercial_email_sequence.updated',
            entityType: CommercialEmailSequence::class,
            entityId: $sequence->id,
            description: "Sequência de e-mail atualizada: {$sequence->name}",
        );

        return new CommercialEmailSequenceResource($sequence->load('steps.template'));
    }
}
