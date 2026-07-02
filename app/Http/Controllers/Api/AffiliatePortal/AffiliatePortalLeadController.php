<?php

namespace App\Http\Controllers\Api\AffiliatePortal;

use App\Http\Controllers\Controller;
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

class AffiliatePortalLeadController extends Controller
{
    public function __construct(
        protected CommercialLeadDuplicateService $duplicateService,
        protected CommercialCommissionService $commissionService,
        protected AuditLogService $auditLogService,
    ) {}

    public function index(Request $request)
    {
        $affiliateId = $this->resolveAffiliateId($request);

        $query = CommercialLead::query()
            ->where('affiliate_id', $affiliateId)
            ->with(['currentStep', 'assignedToUser', 'createdByUser', 'affiliate']);

        $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->input('priority')))
            ->when($request->filled('current_step_id'), fn ($q) => $q->where('current_step_id', $request->input('current_step_id')))
            ->when($request->filled('is_overdue'), fn ($q) => $q->overdue($request->boolean('is_overdue')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->input('search').'%';
                $q->where(function ($q) use ($term) {
                    $q->where('company_name', 'like', $term)
                        ->orWhere('contact_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            });

        return CommercialLeadResource::collection(
            $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20))
        );
    }

    public function store(CommercialLeadStoreRequest $request)
    {
        $this->authorize('create', CommercialLead::class);

        $affiliateId = $this->resolveAffiliateId($request);

        $data = $request->validated();
        $data['affiliate_id'] = $affiliateId;

        $duplicates = $this->duplicateService->findDuplicates($data);

        $lead = CommercialLead::create($data);

        $this->auditLogService->log(
            action: 'lead.created',
            entityType: CommercialLead::class,
            entityId: $lead->id,
            description: "Lead criado pelo afiliado: {$lead->company_name}",
            newValues: $this->auditLogService->snapshot($lead),
        );

        return (new CommercialLeadResource($lead))
            ->additional([
                'duplicate_warning' => $duplicates->isNotEmpty(),
                'possible_duplicates' => $duplicates->map(fn ($d) => [
                    'id' => $d->id,
                    'company_name' => $d->company_name,
                ]),
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, string $id)
    {
        $affiliateId = $this->resolveAffiliateId($request);

        $lead = CommercialLead::where('affiliate_id', $affiliateId)
            ->with(['currentStep', 'assignedToUser', 'createdByUser', 'affiliate', 'notes.user', 'stepLogs.step', 'stepLogs.user'])
            ->findOrFail($id);

        return new CommercialLeadResource($lead);
    }

    public function update(CommercialLeadUpdateRequest $request, string $id)
    {
        $affiliateId = $this->resolveAffiliateId($request);

        $lead = CommercialLead::where('affiliate_id', $affiliateId)->findOrFail($id);

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

    public function addNote(CommercialLeadNoteRequest $request, string $id)
    {
        $affiliateId = $this->resolveAffiliateId($request);

        $lead = CommercialLead::where('affiliate_id', $affiliateId)->findOrFail($id);

        $this->authorize('addNote', $lead);

        $note = CommercialLeadNote::create([
            'lead_id' => $lead->id,
            'user_id' => null,
            'note' => $request->validated()['note'],
        ]);

        return (new CommercialLeadNoteResource($note))->response()->setStatusCode(201);
    }

    public function nextAction(CommercialLeadNextActionRequest $request, string $id)
    {
        $affiliateId = $this->resolveAffiliateId($request);

        $lead = CommercialLead::where('affiliate_id', $affiliateId)->findOrFail($id);

        $this->authorize('update', $lead);

        $lead->update($request->validated());

        return new CommercialLeadResource($lead);
    }

    public function moveStep(CommercialLeadMoveStepRequest $request, string $id)
    {
        $affiliateId = $this->resolveAffiliateId($request);

        $lead = CommercialLead::where('affiliate_id', $affiliateId)->findOrFail($id);

        $this->authorize('moveStep', $lead);

        $data = $request->validated();
        $lead->update(['current_step_id' => $data['step_id']]);

        CommercialLeadStepLog::create([
            'lead_id' => $lead->id,
            'step_id' => $data['step_id'],
            'user_id' => null,
            'status' => 'done',
            'note' => $data['note'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'completed_at' => now(),
        ]);

        return new CommercialLeadResource($lead->load('currentStep'));
    }

    public function markWon(CommercialLeadMarkWonRequest $request, string $id)
    {
        $affiliateId = $this->resolveAffiliateId($request);

        $lead = CommercialLead::where('affiliate_id', $affiliateId)->findOrFail($id);

        $this->authorize('markWon', $lead);

        $data = $request->validated();

        $lead->update([
            'status' => 'won',
            'converted_at' => now(),
            'customer_id' => $data['customer_id'] ?? $lead->customer_id,
        ]);

        if (! empty($data['base_amount'])) {
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
        $affiliateId = $this->resolveAffiliateId($request);

        $lead = CommercialLead::where('affiliate_id', $affiliateId)->findOrFail($id);

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

    private function resolveAffiliateId(Request $request): string
    {
        return (string) $request->user()->id;
    }
}
