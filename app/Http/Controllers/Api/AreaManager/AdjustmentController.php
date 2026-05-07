<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use App\Services\UserVisibilityService;
use Illuminate\Http\Request;

class AdjustmentController extends Controller
{
    public function __construct(
        protected UserVisibilityService $userVisibilityService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $this->authorize('viewAnyAdjustments', TimeEntry::class);

        $query = TimeEntry::query()
            ->with('user:id,name,email')
            ->whereNotNull('adjustment_status')
            ->where('adjustment_status', '!=', 'rejected')
            ->orderByDesc('adjustment_requested_at');
        $this->userVisibilityService->applyToUserOwnedQuery($query, $user);

        if ($request->filled('status')) {
            $query->where('adjustment_status', $request->status);
        } else {
            $query->where('adjustment_status', 'pending');
        }

        if ($request->filled('user_id')) {
            if ($user->hasRole('admin') || $this->userVisibilityService->canManageUserId($user, $request->user_id)) {
                $query->where('user_id', $request->user_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 15))
        );
    }
}
