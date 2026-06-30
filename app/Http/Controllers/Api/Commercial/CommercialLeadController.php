<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\CommercialLeadAssignRequest;
use App\Http\Requests\Commercial\CommercialLeadMarkLostRequest;
use App\Http\Requests\Commercial\CommercialLeadMarkWonRequest;
use App\Http\Requests\Commercial\CommercialLeadMoveStepRequest;
use App\Http\Requests\Commercial\CommercialLeadNextActionRequest;
use App\Http\Requests\Commercial\CommercialLeadNoteRequest;
use App\Http\Requests\Commercial\CommercialLeadStoreRequest;
use App\Http\Requests\Commercial\CommercialLeadUpdateRequest;
use App\Http\Resources\Commercial\CommercialLeadNoteResource;
use App\Http\Resources\Commercial\CommercialLeadResource;
use App\Models\CommercialLead;
use App\Models\CommercialLeadNote;
use App\Models\CommercialLeadStepLog;
use App\Services\AuditLogService;
use App\Services\Commercial\CommercialCommissionService;
use App\Services\Commercial\CommercialLeadDuplicateService;
use Illuminate\Http\Request;

class CommercialLeadController extends Controller
{
    public function __construct(
        protected CommercialLeadDuplicateService $duplicateService,
        protected CommercialCommissionService $commissionService,
        protected AuditLogService $auditLogService,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', CommercialLead::class);

        $query = CommercialLead::query()
            ->with(['currentStep', 'assignedToUser', 'affiliate']);

        $this->applyFilters($query, $request);

        return CommercialLeadResource::collection(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20))
        );
    }

    public function store(CommercialLeadStoreRequest $request)
    {
        $this->authorize('create', CommercialLead::class);

        $data = $request->validated();
        $data['created_by_user_id'] = $request->user()->id;

        $duplicates = $this->duplicateService->findDuplicates($data);

        $lead = CommercialLead::create($data);

        $this->auditLogService->log(
            action: 'lead.created',
            entityType: CommercialLead::class,
            entityId: $lead->id,
            description: "Lead criado: {$lead->company_name}",
            newValues: $this->auditLogService->snapshot($lead),
        );

        return (new CommercialLeadResource($lead))
            ->additional([
                'duplicate_warning' => $duplicates->isNotEmpty(),
                'possible_duplicates' => $duplicates->map(fn ($duplicate) => [
                    'id' => $duplicate->id,
                    'company_name' => $duplicate->company_name,
                ]),
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $id)
    {
        $lead = CommercialLead::query()
            ->with(['currentStep', 'assignedToUser', 'createdByUser', 'affiliate', 'notes.user', 'stepLogs.step', 'stepLogs.user'])
            ->findOrFail($id);

        $this->authorize('view', $lead);

        return new CommercialLeadResource($lead);
    }

    public function update(CommercialLeadUpdateRequest $request, string $id)
    {
        $lead = CommercialLead::findOrFail($id);

        $this->authorize('update', $lead);

        $oldValues = $this->auditLogService->snapshot($lead);

        $lead->update($request->validated());

        $this->auditLogService->log(
            action: 'lead.updated',
            entityType: CommercialLead::class,
            entityId: $lead->id,
            description: "Lead atualizado: {$lead->company_name}",
            oldValues: $oldValues,
            newValues: $this->auditLogService->snapshot($lead),
        );

        return new CommercialLeadResource($lead);
    }

    public function destroy(string $id)
    {
        $lead = CommercialLead::findOrFail($id);

        $this->authorize('delete', $lead);

        $lead->delete();

        $this->auditLogService->log(
            action: 'lead.deleted',
            entityType: CommercialLead::class,
            entityId: $lead->id,
            description: "Lead removido: {$lead->company_name}",
        );

        return response()->json(['message' => 'Lead removido com sucesso.']);
    }

    public function assign(CommercialLeadAssignRequest $request, string $id)
    {
        $lead = CommercialLead::findOrFail($id);

        $this->authorize('assign', $lead);

        $oldAssignee = $lead->assigned_to_user_id;
        $lead->update(['assigned_to_user_id' => $request->validated()['assigned_to_user_id']]);

        $this->auditLogService->log(
            action: 'lead.assigned',
            entityType: CommercialLead::class,
            entityId: $lead->id,
            description: "Responsável alterado de {$oldAssignee} para {$lead->assigned_to_user_id}",
        );

        return new CommercialLeadResource($lead);
    }

    public function moveStep(CommercialLeadMoveStepRequest $request, string $id)
    {
        $lead = CommercialLead::findOrFail($id);

        $this->authorize('moveStep', $lead);

        $data = $request->validated();
        $oldStepId = $lead->current_step_id;

        $lead->update(['current_step_id' => $data['step_id']]);

        CommercialLeadStepLog::create([
            'lead_id' => $lead->id,
            'step_id' => $data['step_id'],
            'user_id' => $request->user()->id,
            'status' => 'done',
            'note' => $data['note'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'completed_at' => now(),
        ]);

        $this->auditLogService->log(
            action: 'lead.step_changed',
            entityType: CommercialLead::class,
            entityId: $lead->id,
            description: "Etapa alterada de {$oldStepId} para {$data['step_id']}",
        );

        return new CommercialLeadResource($lead->load('currentStep'));
    }

    public function addNote(CommercialLeadNoteRequest $request, string $id)
    {
        $lead = CommercialLead::findOrFail($id);

        $this->authorize('addNote', $lead);

        $note = CommercialLeadNote::create([
            'lead_id' => $lead->id,
            'user_id' => $request->user()->id,
            'note' => $request->validated()['note'],
        ]);

        $this->auditLogService->log(
            action: 'lead.note_added',
            entityType: CommercialLead::class,
            entityId: $lead->id,
            description: 'Observação adicionada ao lead',
        );

        return (new CommercialLeadNoteResource($note))->response()->setStatusCode(201);
    }

    public function nextAction(CommercialLeadNextActionRequest $request, string $id)
    {
        $lead = CommercialLead::findOrFail($id);

        $this->authorize('update', $lead);

        $lead->update($request->validated());

        $this->auditLogService->log(
            action: 'lead.next_action_set',
            entityType: CommercialLead::class,
            entityId: $lead->id,
            description: "Próxima ação definida: {$lead->next_action_type}",
        );

        return new CommercialLeadResource($lead);
    }

    public function markWon(CommercialLeadMarkWonRequest $request, string $id)
    {
        $lead = CommercialLead::findOrFail($id);

        $this->authorize('markWon', $lead);

        $data = $request->validated();

        $lead->update([
            'status' => 'won',
            'converted_at' => now(),
            'customer_id' => $data['customer_id'] ?? $lead->customer_id,
        ]);

        if ($lead->affiliate_id && ! empty($data['base_amount'])) {
            $this->commissionService->generateRecurringCommissions($lead, (float) $data['base_amount']);
        }

        $this->auditLogService->log(
            action: 'lead.converted',
            entityType: CommercialLead::class,
            entityId: $lead->id,
            description: "Lead convertido em cliente: {$lead->company_name}",
        );

        return new CommercialLeadResource($lead);
    }

    public function markLost(CommercialLeadMarkLostRequest $request, string $id)
    {
        $lead = CommercialLead::findOrFail($id);

        $this->authorize('markLost', $lead);

        $lead->update([
            'status' => 'lost',
            'lost_reason' => $request->validated()['lost_reason'] ?? null,
        ]);

        $this->auditLogService->log(
            action: 'lead.lost',
            entityType: CommercialLead::class,
            entityId: $lead->id,
            description: "Lead perdido: {$lead->company_name}",
        );

        return new CommercialLeadResource($lead);
    }

    private function applyFilters($query, Request $request): void
    {
        $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->input('priority')))
            ->when($request->filled('current_step_id'), fn ($q) => $q->where('current_step_id', $request->input('current_step_id')))
            ->when($request->filled('assigned_to_user_id'), fn ($q) => $q->where('assigned_to_user_id', $request->input('assigned_to_user_id')))
            ->when($request->filled('affiliate_id'), fn ($q) => $q->where('affiliate_id', $request->input('affiliate_id')))
            ->when($request->filled('country'), fn ($q) => $q->where('country', $request->input('country')))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->input('city')))
            ->when($request->filled('segment'), fn ($q) => $q->where('segment', $request->input('segment')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->when($request->filled('next_action_from'), fn ($q) => $q->where('next_action_at', '>=', $request->input('next_action_from')))
            ->when($request->filled('next_action_to'), fn ($q) => $q->where('next_action_at', '<=', $request->input('next_action_to')))
            ->when($request->filled('created_from'), fn ($q) => $q->where('created_at', '>=', $request->input('created_from')))
            ->when($request->filled('created_to'), fn ($q) => $q->where('created_at', '<=', $request->input('created_to')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->input('search').'%';

                $q->where(function ($q) use ($term) {
                    $q->where('company_name', 'like', $term)
                        ->orWhere('contact_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('whatsapp', 'like', $term)
                        ->orWhere('website', 'like', $term);
                });
            });
    }
}
