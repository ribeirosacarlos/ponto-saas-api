<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminAnnouncementStoreRequest;
use App\Http\Requests\AdminAnnouncementUpdateRequest;
use App\Http\Resources\AnnouncementDetailResource;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAnyAdmin', Announcement::class);

        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'type' => 'nullable|string|in:general,holiday,vacation,tip',
            'query' => 'nullable|string',
        ]);

        $user = $request->user();

        $query = Announcement::with('creator.roles')
            ->where('company_id', $user->company_id);

        if ($request->filled('from')) {
            $query->whereDate('sent_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('sent_at', '<=', $request->input('to'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('query')) {
            $query->where('title', 'like', '%' . $request->input('query') . '%');
        }

        $announcements = $query
            ->orderByDesc('sent_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        return AnnouncementResource::collection($announcements);
    }

    public function store(AdminAnnouncementStoreRequest $request)
    {
        $this->authorize('create', Announcement::class);

        $user = $request->user();

        $announcement = Announcement::create([
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'title' => $request->title,
            'summary' => $request->summary,
            'body' => $request->body,
            'type' => $request->type,
            'sent_at' => $request->sent_at,
        ]);

        return (new AnnouncementDetailResource($announcement->load('creator.roles')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Announcement $announcement)
    {
        $this->authorize('view', $announcement);

        return new AnnouncementDetailResource(
            $announcement->loadMissing('creator.roles')
        );
    }

    public function update(AdminAnnouncementUpdateRequest $request, Announcement $announcement)
    {
        $this->authorize('update', $announcement);

        $announcement->update($request->validated());

        return new AnnouncementDetailResource(
            $announcement->refresh()->loadMissing('creator.roles')
        );
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        return response()->json(['message' => 'Comunicado removido.']);
    }
}
