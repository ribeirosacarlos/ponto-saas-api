<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Resources\Commercial\CommercialEmailSendResource;
use App\Models\CommercialEmailSend;
use App\Models\CommercialLead;
use Illuminate\Http\Request;

class CommercialEmailSendController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', CommercialLead::class);

        $user = $request->user();

        $query = CommercialEmailSend::query()
            ->with(['lead', 'template', 'sequenceStep.sequence'])
            ->when(
                $user?->hasRole('commercial_agent'),
                fn ($q) => $q->whereHas('lead', fn ($q2) => $q2->where('assigned_to_user_id', $user->id))
            )
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('email'), fn ($q) => $q->where('to_email', 'like', '%'.$request->input('email').'%'))
            ->when($request->filled('enrollment_id'), fn ($q) => $q->where('enrollment_id', $request->input('enrollment_id')))
            ->orderByDesc('created_at');

        return CommercialEmailSendResource::collection(
            $query->paginate($request->integer('per_page', 20))
        );
    }
}
