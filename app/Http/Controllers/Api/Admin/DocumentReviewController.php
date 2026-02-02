<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Documents\Traits\LogsDocumentAudits;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminPendingIndexRequest;
use App\Http\Requests\DocumentRejectRequest;
use App\Http\Resources\DocumentAdminResource;
use App\Models\Document;
use App\Models\DocumentNotification;

class DocumentReviewController extends Controller
{
    use LogsDocumentAudits;

    public function pending(AdminPendingIndexRequest $request)
    {
        $this->authorize('adminList', Document::class);

        $documents = $this->buildQuery($request)
            ->where('status', Document::STATUS_PENDING)
            ->paginate($request->input('per_page', 20))
            ->appends($request->query());

        return DocumentAdminResource::collection($documents);
    }

    public function review(AdminPendingIndexRequest $request)
    {
        $this->authorize('adminList', Document::class);

        $documents = $this->buildQuery($request)
            ->where('status', Document::STATUS_REVIEW)
            ->paginate($request->input('per_page', 20))
            ->appends($request->query());

        return DocumentAdminResource::collection($documents);
    }

    public function show(Document $document)
    {
        $this->authorize('adminShow', $document);

        return new DocumentAdminResource($document->load('user'));
    }

    public function approve(Document $document)
    {
        $this->authorize('adminApprove', $document);

        $oldStatus = $document->status;
        $document->status = Document::STATUS_AVAILABLE;
        $document->rejected_comment = null;
        $document->rejected_by = null;
        $document->rejected_at = null;
        $document->save();

        $this->logDocumentAudit($document, 'status_change', [
            'from' => $oldStatus,
            'to' => Document::STATUS_AVAILABLE,
        ]);
        $this->logDocumentAudit($document, 'approve');

        $this->createNotification($document, 'approved', 'Documento aprovado');

        return new DocumentAdminResource($document->fresh()->load('user'));
    }

    public function reject(DocumentRejectRequest $request, Document $document)
    {
        $this->authorize('adminReject', $document);

        $oldStatus = $document->status;
        $comment = $request->input('comment');

        $document->status = Document::STATUS_REVIEW;
        $document->rejected_comment = $comment;
        $document->rejected_by = auth()->id();
        $document->rejected_at = now();
        $document->save();

        $this->logDocumentAudit($document, 'status_change', [
            'from' => $oldStatus,
            'to' => Document::STATUS_REVIEW,
        ]);

        $this->logDocumentAudit($document, 'comment', [
            'comment' => $comment,
        ]);

        $this->createNotification($document, 'rejected', $comment);

        return new DocumentAdminResource($document->fresh()->load('user'));
    }

    private function buildQuery(AdminPendingIndexRequest $request)
    {
        $query = Document::with('user:id,name,email')->where('company_id', $request->user()->company_id);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('employee_id')) {
            $query->where('user_id', $request->input('employee_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        [$sortField, $sortDirection] = $this->parseSort($request->input('sort'));
        $query->orderBy($sortField, $sortDirection);

        return $query;
    }

    private function parseSort(?string $sort): array
    {
        $sort = $sort ?: 'updated_at:desc';
        [$field, $direction] = array_pad(explode(':', $sort, 2), 2, '');

        $allowed = ['updated_at'];
        $field = in_array($field, $allowed, true) ? $field : 'updated_at';
        $direction = in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : 'desc';

        return [$field, $direction];
    }

    private function createNotification(Document $document, string $type, string $message)
    {
        DocumentNotification::create([
            'company_id' => $document->company_id,
            'user_id' => $document->user_id,
            'document_id' => $document->id,
            'type' => $type,
            'message' => $message,
        ]);
    }
}
