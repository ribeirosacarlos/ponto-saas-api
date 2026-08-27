<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\CommercialEmailEnrollmentPauseRequest;
use App\Http\Requests\Commercial\CommercialEmailEnrollmentStoreRequest;
use App\Http\Resources\Commercial\CommercialEmailSequenceEnrollmentResource;
use App\Models\CommercialEmailSequence;
use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\CommercialLead;
use App\Services\AuditLogService;
use App\Services\Commercial\CommercialEmailEnrollmentService;
use Illuminate\Validation\ValidationException;

class CommercialEmailEnrollmentController extends Controller
{
    public function __construct(
        protected CommercialEmailEnrollmentService $enrollmentService,
        protected AuditLogService $auditLogService,
    ) {}

    public function store(CommercialEmailEnrollmentStoreRequest $request)
    {
        $this->authorize('viewAny', CommercialLead::class);

        $sequence = CommercialEmailSequence::findOrFail($request->validated()['sequence_id']);
        $user = $request->user();
        $isAgent = $user->hasRole('commercial_agent');

        $results = [];
        $enrolledCount = 0;

        foreach ($request->validated()['lead_ids'] as $index => $leadId) {
            $lead = CommercialLead::find($leadId);

            if (! $lead || ($isAgent && (string) $lead->assigned_to_user_id !== (string) $user->id)) {
                $results[] = ['index' => $index, 'lead_id' => $leadId, 'status' => 'error', 'errors' => ['lead_id' => ['Lead não encontrado ou fora do seu escopo.']]];

                continue;
            }

            try {
                $enrollment = $this->enrollmentService->enroll($lead, $sequence, $user);
            } catch (ValidationException $e) {
                $results[] = ['index' => $index, 'lead_id' => $leadId, 'status' => 'error', 'errors' => $e->errors()];

                continue;
            }

            $this->auditLogService->log(
                action: 'commercial_email.enrolled',
                entityType: CommercialEmailSequenceEnrollment::class,
                entityId: $enrollment->id,
                description: "Lead {$lead->company_name} inscrito na sequência {$sequence->name}",
            );

            $enrolledCount++;
            $results[] = ['index' => $index, 'lead_id' => $leadId, 'status' => 'enrolled', 'enrollment' => new CommercialEmailSequenceEnrollmentResource($enrollment)];
        }

        return response()->json([
            'data' => $results,
            'meta' => [
                'total' => count($request->validated()['lead_ids']),
                'enrolled' => $enrolledCount,
                'failed' => count($request->validated()['lead_ids']) - $enrolledCount,
            ],
        ], 207);
    }

    public function pause(CommercialEmailEnrollmentPauseRequest $request, string $id)
    {
        $enrollment = $this->findVisibleEnrollment($id);

        $this->authorize('manage', $enrollment);

        $this->enrollmentService->pause($enrollment, $request->user(), $request->validated()['reason'] ?? null);

        $this->auditLogService->log(
            action: 'commercial_email.paused',
            entityType: CommercialEmailSequenceEnrollment::class,
            entityId: $enrollment->id,
            description: 'Inscrição em sequência de e-mail pausada',
        );

        return new CommercialEmailSequenceEnrollmentResource($enrollment->fresh());
    }

    public function resume(string $id)
    {
        $enrollment = $this->findVisibleEnrollment($id);

        $this->authorize('manage', $enrollment);

        $this->enrollmentService->resume($enrollment);

        $this->auditLogService->log(
            action: 'commercial_email.resumed',
            entityType: CommercialEmailSequenceEnrollment::class,
            entityId: $enrollment->id,
            description: 'Inscrição em sequência de e-mail retomada',
        );

        return new CommercialEmailSequenceEnrollmentResource($enrollment->fresh());
    }

    public function cancel(string $id)
    {
        $enrollment = $this->findVisibleEnrollment($id);

        $this->authorize('manage', $enrollment);

        $this->enrollmentService->cancel($enrollment, 'manual_cancel');

        $this->auditLogService->log(
            action: 'commercial_email.cancelled',
            entityType: CommercialEmailSequenceEnrollment::class,
            entityId: $enrollment->id,
            description: 'Inscrição em sequência de e-mail cancelada manualmente',
        );

        return new CommercialEmailSequenceEnrollmentResource($enrollment->fresh());
    }

    public function markReplied(string $id)
    {
        $enrollment = $this->findVisibleEnrollment($id);

        $this->authorize('manage', $enrollment);

        $this->enrollmentService->markReplied($enrollment, request()->user());

        $this->auditLogService->log(
            action: 'commercial_email.marked_replied',
            entityType: CommercialEmailSequenceEnrollment::class,
            entityId: $enrollment->id,
            description: 'Lead marcado como respondido — sequência encerrada',
        );

        return new CommercialEmailSequenceEnrollmentResource($enrollment->fresh());
    }

    private function findVisibleEnrollment(string $id): CommercialEmailSequenceEnrollment
    {
        $user = request()->user();

        return CommercialEmailSequenceEnrollment::query()
            ->with('lead')
            ->when(
                $user?->hasRole('commercial_agent'),
                fn ($query) => $query->whereHas('lead', fn ($q) => $q->where('assigned_to_user_id', $user->id))
            )
            ->findOrFail($id);
    }
}
