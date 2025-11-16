<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TimeEntry;

class TimeEntryController extends Controller
{
    public function teamEntries(Request $request)
    {
        $entries = TimeEntry::with('user')
            ->orderBy('clocked_at', 'desc')
            ->paginate(30);

        return response()->json($entries);
    }
}
