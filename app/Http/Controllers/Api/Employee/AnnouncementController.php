<?php

namespace App\Http\Controllers\Api\Employee;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementDetailResource;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\AnnouncementRead;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAnyEmployee', Announcement::class);

        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'status' => 'nullable|in:pending,seen',
        ]);

        $user = $request->user();

        $query = Announcement::with([
            'creator.roles',
            'reads' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            },
        ])->where('company_id', $user->company_id);

        if ($request->filled('from')) {
            $query->whereDate('sent_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('sent_at', '<=', $request->input('to'));
        }

        if ($request->filled('status')) {
            if ($request->status === 'seen') {
                $query->whereHas('reads', function ($sub) use ($user) {
                    $sub->where('user_id', $user->id)->whereNotNull('seen_at');
                });
            } elseif ($request->status === 'pending') {
                $query->whereDoesntHave('reads', function ($sub) use ($user) {
                    $sub->where('user_id', $user->id)->whereNotNull('seen_at');
                });
            }
        }

        $announcements = $query
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return AnnouncementResource::collection($announcements);
    }

    public function show(Request $request, Announcement $announcement)
    {
        $this->authorize('view', $announcement);

        $announcement->loadMissing([
            'creator.roles',
            'reads' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            },
        ]);

        return new AnnouncementDetailResource($announcement);
    }

    public function markAsSeen(Request $request, Announcement $announcement)
    {
        $this->authorize('markSeen', $announcement);

        $user = $request->user();

        $read = AnnouncementRead::updateOrCreate(
            [
                'company_id' => $user->company_id,
                'announcement_id' => $announcement->id,
                'user_id' => $user->id,
            ],
            [
                'seen_at' => now(),
            ]
        );

        return response()->json([
            'seenAt' => $read->seen_at?->toIso8601String(),
            'status' => 'seen',
        ]);
    }

    public function pendingCount(Request $request)
    {
        $this->authorize('viewAnyEmployee', Announcement::class);

        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $user = $request->user();

        $query = Announcement::where('company_id', $user->company_id)
            ->whereDoesntHave('reads', function ($sub) use ($user) {
                $sub->where('user_id', $user->id)->whereNotNull('seen_at');
            });

        if ($request->filled('from')) {
            $query->whereDate('sent_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('sent_at', '<=', $request->input('to'));
        }

        return response()->json(['count' => $query->count()]);
    }
}
