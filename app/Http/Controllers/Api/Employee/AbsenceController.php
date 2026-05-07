<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use Illuminate\Http\Request;

class AbsenceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Absence::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->orderByDesc('start_date');

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

        return response()->json($query->paginate($request->integer('per_page', 20)));
    }
}
