<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TimeEntry;

class TimeEntryController extends Controller
{
    public function clock(Request $request)
    {
        $request->validate([
            'type' => 'required|in:in,out',
            'latitude' => 'nullable|string',
            'longitude' => 'nullable|string',
        ]);

        $entry = TimeEntry::create([
            'user_id' => $request->user()->id,
            'clocked_at' => now(),
            'type' => $request->type,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'source' => 'web',
        ]);

        return response()->json($entry, 201);
    }

    public function myEntries(Request $request)
    {
        $entries = $request->user()
            ->timeEntries()
            ->orderBy('clocked_at', 'desc')
            ->paginate(20);

        return response()->json($entries);
    }
}
