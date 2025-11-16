<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TimeEntry;
use App\Http\Requests\TimeEntryStoreRequest;

class TimeEntryController extends Controller
{
    public function teamEntries(TimeEntryStoreRequest $request)
    {
        $entries = TimeEntry::with('user')
            ->orderBy('clocked_at', 'desc')
            ->paginate(30);

        foreach ($entries as $entry) {
            $this->authorize('view', $entry);
        }

        return response()->json($entries);
    }
}
