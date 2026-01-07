<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Adjustment;

class AdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $this->authorize('viewAny', Adjustment::class);

        $query = Adjustment::query()
            ->where('company_id', $user->company_id)
            ->with([
                'user:id,name,email',
                'approver:id,name'
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending');
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json(
            $query->paginate(15)
        );
    }

    public function approve($id, Request $request)
    {
        $adj = Adjustment::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $this->authorize('approve', $adj);

        $adj->update([
            'status'      => 'approved',
            'approver_id' => $request->user()->id
        ]);

        return response()->json($adj);
    }

    public function reject($id, Request $request)
    {
        $adj = Adjustment::where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $this->authorize('reject', $adj);

        $adj->update([
            'status'      => 'rejected',
            'approver_id' => $request->user()->id
        ]);

        return response()->json($adj);
    }
}
