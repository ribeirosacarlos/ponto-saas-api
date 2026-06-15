<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\TimesheetStatus;
use App\Http\Controllers\Api\Documents\Traits\LogsDocumentAudits;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminDocumentStoreRequest;
use App\Http\Requests\AdminPendingIndexRequest;
use App\Http\Requests\DocumentRejectRequest;
use App\Http\Resources\DocumentAdminResource;
use App\Http\Resources\PendingDocumentItemResource;
use App\Http\Resources\TimesheetSignaturePendingResource;
use App\Models\Document;
use App\Models\DocumentNotification;
use App\Models\EmployeeTimesheet;
use App\Models\User;
use App\Services\UserVisibilityService;
use App\Support\DocumentStoragePath;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DocumentReviewController extends Controller
{
    use LogsDocumentAudits;

    public function __construct(
        protected UserVisibilityService $userVisibilityService
    ) {}

    public function pending(AdminPendingIndexRequest $request)
    {
        $this->authorize('adminList', Document::class);

        $items = $this->pendingDocumentItems($request)
            ->concat($this->pendingTimesheetSignatureItems($request));

        [$sortField, $sortDirection] = $this->parseSort($request->input('sort'));
        $items = $items->sortBy($sortField, SORT_REGULAR, $sortDirection === 'desc')->values();

        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);

        $paginator = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url()]
        );
        $paginator->appends($request->query());

        return PendingDocumentItemResource::collection($paginator);
    }

    private function pendingDocumentItems(AdminPendingIndexRequest $request)
    {
        $documents = $this->buildQuery($request)
            ->where('status', Document::STATUS_PENDING)
            ->get();

        return $documents->map(fn (Document $document) => (new DocumentAdminResource($document))->toArray($request));
    }

    private function pendingTimesheetSignatureItems(AdminPendingIndexRequest $request)
    {
        // Filtros de documento (category/search) não se aplicam a folhas de ponto.
        if ($request->filled('category') || $request->filled('search')) {
            return collect();
        }

        $query = EmployeeTimesheet::query()
            ->where('company_id', $request->user()->company_id)
            ->where('status', TimesheetStatus::PENDING_MANAGER->value)
            ->with(['employee:id,name,email,area_id', 'monthlyClosure']);

        $this->userVisibilityService->applyToUserOwnedQuery($query, $request->user(), 'employee_id', null);

        if ($request->filled('employee_id')) {
            if ($request->user()->hasRole('admin') || $this->userVisibilityService->canManageUserId($request->user(), $request->input('employee_id'))) {
                $query->where('employee_id', $request->input('employee_id'));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->get()->map(fn (EmployeeTimesheet $timesheet) => (new TimesheetSignaturePendingResource($timesheet))->toArray($request));
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

        return new DocumentAdminResource($document->load(['user', 'absences']));
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

        return new DocumentAdminResource($document->fresh()->load(['user', 'absences']));
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

        return new DocumentAdminResource($document->fresh()->load(['user', 'absences']));
    }

    public function uploadForEmployee(AdminDocumentStoreRequest $request)
    {
        $admin = $request->user();
        $this->authorize('adminList', Document::class);

        $targetUserQuery = $admin->hasRole('admin')
            ? User::query()->where('company_id', $admin->company_id)
            : $this->userVisibilityService->visibleUsersQuery($admin);

        $targetUser = $targetUserQuery->whereKey($request->input('user_id'))->firstOrFail();

        $created = [];
        $disk = 's3';

        foreach ($request->file('files', []) as $file) {
            [$path, $extension] = DocumentStoragePath::newEmployeeDocumentPath(
                $file,
                $targetUser->company_id,
                $targetUser->id,
            );

            $stream = fopen($file->getRealPath(), 'rb');
            $uploaded = $stream
                ? Storage::disk($disk)->put($path, $stream, [
                    'visibility' => 'private',
                    'ContentType' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
                ])
                : false;

            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $uploaded) {
                throw new RuntimeException('Não foi possível salvar o arquivo.');
            }

            try {
                $document = Document::create([
                    'company_id' => $targetUser->company_id,
                    'user_id' => $targetUser->id,
                    'title' => $this->resolveTitle($file->getClientOriginalName(), $request->input('title')),
                    'category' => $request->input('category'),
                    'status' => Document::STATUS_PENDING,
                    'mime_type' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
                    'ext' => $extension,
                    'size_bytes' => $file->getSize() ?: 0,
                    'path' => $path,
                    'storage_disk' => $disk,
                    'original_name' => $file->getClientOriginalName(),
                    'uploaded_by' => $admin->id,
                    'notes' => $request->input('notes'),
                ]);
            } catch (Throwable $exception) {
                Storage::disk($disk)->delete($path);
                throw $exception;
            }

            $this->logDocumentAudit($document, 'admin_upload', [
                'original_name' => $file->getClientOriginalName(),
                'uploaded_by_admin' => $admin->id,
                'target_user_id' => $targetUser->id,
            ]);

            $created[] = $document;
        }

        $resource = DocumentAdminResource::collection(collect($created))->response();

        return $resource->setStatusCode(201);
    }

    private function buildQuery(AdminPendingIndexRequest $request)
    {
        $query = Document::with(['user:id,name,email,area_id', 'absences']);
        $this->userVisibilityService->applyToUserOwnedQuery($query, $request->user());

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('employee_id')) {
            if ($request->user()->hasRole('admin') || $this->userVisibilityService->canManageUserId($request->user(), $request->input('employee_id'))) {
                $query->where('user_id', $request->input('employee_id'));
            } else {
                $query->whereRaw('1 = 0');
            }
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

    private function resolveTitle(string $originalName, ?string $prefix): string
    {
        $title = trim($prefix ? sprintf('%s - %s', $prefix, $originalName) : $originalName);

        return Str::limit($title, 180);
    }
}
