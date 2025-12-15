<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use App\Models\TimeEntry;
use App\Http\Requests\TimeEntryStoreRequest;
use App\Models\VacationDay;
use App\Services\TimeEntry\OpenStatusService;

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

        $user = $request->user();

        $isOnVacation = VacationDay::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->exists();

        if ($isOnVacation) {
            return response()->json([
                'message' => 'Você está de férias e não pode registrar ponto neste dia.'
            ], 422);
        }

        $lastEntry = $request->user()->timeEntries()->latest('clocked_at')->first();

        if ($lastEntry && $lastEntry->clocked_at->diffInSeconds(now()) < 60) {
            return response()->json(['message' => 'Aguarde 1 minuto entre os registros.'], 422);
        }

        $entry = TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id'    => $user->id,
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

    public function openStatus(Request $request, OpenStatusService $service)
    {
        $status = $service->getStatus($request->user());

        return response()->json($status);
    }
}
