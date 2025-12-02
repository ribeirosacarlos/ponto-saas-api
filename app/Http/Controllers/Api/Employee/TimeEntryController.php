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

        $lastEntry = $request->user()->timeEntries()->latest('clocked_at')->first();

        if ($lastEntry && $lastEntry->clocked_at->diffInSeconds(now()) < 60) {
            return response()->json(['message' => 'Aguarde 1 minuto entre os registros.'], 422);
        }

        $entry = TimeEntry::create([
            'company_id' => $request->user()->company_id,
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
