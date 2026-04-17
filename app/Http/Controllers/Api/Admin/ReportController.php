<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\UserVisibilityService;
use Illuminate\Http\Request;
use App\Models\TimeEntry;
use App\Support\CompanyTime;

class ReportController extends Controller
{
    public function __construct(
        protected UserVisibilityService $userVisibilityService
    ) {
    }

    public function timeReport(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end'   => 'required|date',
        ]);

        $timezone = CompanyTime::companyTz($request);
        $from = CompanyTime::parseToUtc($request->start, $timezone);
        $to = CompanyTime::parseToUtc($request->end, $timezone);

        $query = TimeEntry::with('user')
            ->whereBetween('clocked_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->orderBy('clocked_at');

        $this->userVisibilityService->applyToUserOwnedQuery($query, $request->user());

        return response()->json($query->get());
    }
}
