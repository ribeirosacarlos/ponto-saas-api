<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\AreaManagerTeamEntriesRequest;
use App\Models\TimeEntry;
use App\Support\CompanyTime;

class TimeEntryController extends Controller
{
    public function teamEntries(AreaManagerTeamEntriesRequest $request)
    {
        $user = $request->user();

        $query = TimeEntry::query()
            ->with(['user:id,name,email'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('clocked_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('source')) {
            $sources = array_filter(explode(',', $request->source));
            if (! empty($sources)) {
                $query->whereIn('source', $sources);
            }
        }

        $timezone = CompanyTime::companyTz($request);

        if ($request->filled('date_from')) {
            [$fromUtc] = CompanyTime::dayRangeToUtc($request->date_from, $timezone);
            $query->where('clocked_at', '>=', $fromUtc->toDateTimeString());
        }

        if ($request->filled('date_to')) {
            [, $toUtc] = CompanyTime::dayRangeToUtc($request->date_to, $timezone);
            $query->where('clocked_at', '<=', $toUtc->toDateTimeString());
        }

        $perPage = (int) $request->get('per_page', 30);
        $perPage = max(1, min($perPage, 200));

        $entries = $query->paginate($perPage);

        return response()->json($entries);
    }
}
