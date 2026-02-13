<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use Illuminate\Http\Request;

class AdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $this->authorize('viewAnyAdjustments', TimeEntry::class);

        $query = TimeEntry::query()
            ->with('user:id,name,email')
            ->where('company_id', $user->company_id)
            ->whereNotNull('adjustment_status')
            ->where('adjustment_status', '!=', 'rejected')
            ->orderByDesc('adjustment_requested_at');

        if ($request->filled('status')) {
            $query->where('adjustment_status', $request->status);
        } else {
            $query->where('adjustment_status', 'pending');
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json(
            $query->paginate(15)
        );
    }
}
