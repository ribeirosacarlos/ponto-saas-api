<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TimeEntry;
use App\Support\CompanyTime;

class ReportController extends Controller
{
    public function timeReport(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end'   => 'required|date',
        ]);

        $timezone = CompanyTime::companyTz($request);
        $from = CompanyTime::parseToUtc($request->start, $timezone);
        $to = CompanyTime::parseToUtc($request->end, $timezone);

        $entries = TimeEntry::with('user')
            ->whereBetween('clocked_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->orderBy('clocked_at')
            ->get();

        return response()->json($entries);
    }
}
