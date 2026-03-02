<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAbsenceRequest;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();

        $query = Absence::query()
            ->where('company_id', $admin->company_id)
            ->orderByDesc('start_date');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $from = $request->query('from');
        $to = $request->query('to');

        if ($from || $to) {
            $from = $from ?: $to;
            $to = $to ?: $from;

            $query->where(function ($q) use ($from, $to) {
                $q->whereBetween('start_date', [$from, $to])
                    ->orWhereBetween('end_date', [$from, $to])
                    ->orWhere(function ($q) use ($from, $to) {
                        $q->where('start_date', '<=', $from)
                            ->where(function ($q) use ($to) {
                                $q->whereNull('end_date')->orWhere('end_date', '>=', $to);
                            });
                    });
            });
        }

        return response()->json($query->paginate(20));
    }

    public function store(StoreAbsenceRequest $request)
    {
        $admin = $request->user();
        $user = User::where('company_id', $admin->company_id)->findOrFail($request->user_id);

        $start = $request->start_date;
        $end = $request->end_date ?: $start;

        $absence = Absence::create([
            'company_id' => $admin->company_id,
            'user_id' => $user->id,
            'type' => $request->type,
            'start_date' => $start,
            'end_date' => $end,
            'status' => $request->status ?? 'recorded',
            'comment' => $request->comment,
            'counts_for_accrual' => $request->counts_for_accrual ?? true,
            'created_by' => $admin->id,
        ]);

        return response()->json($absence, 201);
    }
}
