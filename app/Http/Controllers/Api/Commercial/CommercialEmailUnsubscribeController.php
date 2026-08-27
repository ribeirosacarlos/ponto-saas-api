<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Models\CommercialEmailSequenceEnrollment;
use App\Models\CommercialEmailSuppression;
use App\Services\Commercial\CommercialEmailEnrollmentService;

class CommercialEmailUnsubscribeController extends Controller
{
    public function __construct(protected CommercialEmailEnrollmentService $enrollmentService) {}

    public function handle(string $token)
    {
        $enrollment = CommercialEmailSequenceEnrollment::query()
            ->with('lead')
            ->where('unsubscribe_token', $token)
            ->first();

        if (! $enrollment) {
            return response()->json(['message' => 'Link de descadastro inválido.'], 404);
        }

        $this->enrollmentService->cancel($enrollment, 'unsubscribed');

        if ($enrollment->lead?->email) {
            CommercialEmailSuppression::query()->updateOrCreate(
                ['email' => strtolower(trim($enrollment->lead->email))],
                [
                    'lead_id' => $enrollment->lead_id,
                    'reason' => CommercialEmailSuppression::REASON_UNSUBSCRIBED,
                    'source' => 'unsubscribe_link',
                    'suppressed_at' => now(),
                ]
            );
        }

        return response()->json(['message' => 'Você foi descadastrado com sucesso e não receberá mais e-mails desta empresa.']);
    }
}
