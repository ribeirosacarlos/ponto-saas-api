<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Models\TimeEntry;
use App\Http\Requests\TimeEntryStoreRequest;

class TimeEntryController extends Controller
{
    use AuthorizesRequests;

    public function clock(TimeEntryStoreRequest $request)
    {
        $this->authorize('create', TimeEntry::class);

        $request->validate([
            'type'      => 'required|in:in,out',
            'latitude'  => 'nullable|string',
            'longitude' => 'nullable|string',
        ]);

        $entry = TimeEntry::create([
            'user_id'    => $request->user()->id,
            'clocked_at' => now(),
            'type'       => $request->type,
            'latitude'   => $request->latitude,
            'longitude'  => $request->longitude,
            'source'     => 'web',
        ]);

        return response()->json($entry, 201);
    }

    public function myEntries(Request $request)
    {
        $entries = $request->user()
            ->timeEntries()
            ->orderBy('clocked_at', 'desc')
            ->paginate(20);

        foreach ($entries as $entry) {
            $this->authorize('view', $entry);
        }

        return response()->json($entries);
    }
}
