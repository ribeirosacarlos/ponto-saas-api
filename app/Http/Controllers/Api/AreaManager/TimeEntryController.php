<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use App\Models\TimeEntry;
use App\Http\Requests\TimeEntryStoreRequest;
use Illuminate\Http\Request;

class TimeEntryController extends Controller
{
    public function teamEntries(TimeEntryStoreRequest $request)
    {
        $user = $request->user();

        $query = TimeEntry::query()
            ->with(['user:id,name,email'])
            ->where('company_id', $user->company_id)
            ->orderByDesc('clocked_at');

        // filtros opcionais
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->where('clocked_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('clocked_at', '<=', $request->date_to);
        }

        $perPage = (int) $request->get('per_page', 30);
        $perPage = max(1, min($perPage, 200));

        $entries = $query->paginate($perPage);

        return response()->json($entries);
    }
}
