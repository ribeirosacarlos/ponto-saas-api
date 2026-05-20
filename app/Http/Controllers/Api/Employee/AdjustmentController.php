<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use Illuminate\Http\Request;

class AdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereNotNull('adjustment_status')
            ->orderByDesc('adjustment_requested_at');

        if ($request->filled('status')) {
            $query->where('adjustment_status', $request->status);
        }

        $entries = $query->paginate($request->integer('per_page', 15));
        $entries->setCollection(collect(TimeEntryResource::collectionArray($entries->getCollection())));

        return response()->json($entries);
    }
}
