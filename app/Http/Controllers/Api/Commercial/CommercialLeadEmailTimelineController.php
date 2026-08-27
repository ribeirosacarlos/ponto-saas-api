<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Resources\Commercial\CommercialEmailSequenceEnrollmentResource;
use App\Models\CommercialLead;

class CommercialLeadEmailTimelineController extends Controller
{
    public function show(string $id)
    {
        $user = request()->user();

        $lead = CommercialLead::query()
            ->when(
                $user?->hasRole('commercial_agent'),
                fn ($query) => $query->where('assigned_to_user_id', $user->id)
            )
            ->findOrFail($id);

        $this->authorize('view', $lead);

        $enrollments = $lead->emailSequenceEnrollments()
            ->with(['sequence', 'currentStep', 'nextStep', 'sends' => fn ($q) => $q->orderByDesc('created_at')])
            ->orderByDesc('enrolled_at')
            ->get();

        return response()->json([
            'data' => [
                'is_email_suppressed' => $lead->isEmailSuppressed(),
                'enrollments' => CommercialEmailSequenceEnrollmentResource::collection($enrollments),
            ],
        ]);
    }
}
