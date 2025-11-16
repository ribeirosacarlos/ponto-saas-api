<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TimeEntry;

class ReportController extends Controller
{
    public function timeReport(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end'   => 'required|date',
        ]);

        $entries = TimeEntry::with('user')
            ->whereBetween('clocked_at', [$request->start, $request->end])
            ->orderBy('clocked_at')
            ->get();

        return response()->json($entries);
    }
}
