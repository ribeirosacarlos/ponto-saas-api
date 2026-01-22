<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Adjustment;
use App\Models\TimeEntry;

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

        DB::transaction(function () use ($adj, $request) {
            $adj->update([
                'status'      => 'approved',
                'approver_id' => $request->user()->id,
            ]);

            $this->createTimeEntryFromAdjustment($adj);
        });

        return response()->json($adj->refresh());
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

    private function createTimeEntryFromAdjustment(Adjustment $adjustment): ?TimeEntry
    {
        if (! $adjustment->corrected_time) {
            return null;
        }

        $exists = TimeEntry::where('user_id', $adjustment->user_id)
            ->where('clocked_at', $adjustment->corrected_time)
            ->exists();

        if ($exists) {
            return null;
        }

        return TimeEntry::create([
            'company_id' => $adjustment->company_id,
            'user_id' => $adjustment->user_id,
            'clocked_at' => $adjustment->corrected_time,
            'type' => $this->guessTimeEntryType($adjustment),
            'source' => 'adjustment',
        ]);
    }

    private function guessTimeEntryType(Adjustment $adjustment): string
    {
        $clockedAt = $adjustment->corrected_time;

        if (! $clockedAt) {
            return 'in';
        }

        $date = Carbon::parse($clockedAt)->toDateString();

        $entries = TimeEntry::where('company_id', $adjustment->company_id)
            ->where('user_id', $adjustment->user_id)
            ->whereDate('clocked_at', $date)
            ->where('clocked_at', '<=', $clockedAt)
            ->orderBy('clocked_at')
            ->get();

        $pendingIn = false;

        foreach ($entries as $entry) {
            if ($entry->type === 'in') {
                $pendingIn = true;
                continue;
            }

            if ($entry->type === 'out') {
                $pendingIn = false;
            }
        }

        return $pendingIn ? 'out' : 'in';
    }

}
