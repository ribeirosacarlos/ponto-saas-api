<?php

namespace App\Http\Controllers\Api\Documents;

use App\Http\Controllers\Api\Documents\Traits\LogsDocumentAudits;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentResendRequest;
use App\Http\Requests\DocumentStoreRequest;
use App\Http\Requests\DocumentUpdateRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\User;
use App\Services\UserVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DocumentController extends Controller
{
    use LogsDocumentAudits;

    private const PRIVILEGED_ROLES = ['admin', 'manager', 'area_manager'];
    private const SORT_FIELDS = ['updated_at'];
    private const DEFAULT_SORT_FIELD = 'updated_at';
    private const DEFAULT_SORT_DIRECTION = 'desc';
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;

    public function __construct(
        protected UserVisibilityService $userVisibilityService
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Document::query();

        if ($this->isPrivileged($user) && $request->filled('user_id')) {
            if ($this->userVisibilityService->canManageUserId($user, $request->input('user_id'))) {
                $query->where('user_id', $request->input('user_id'));
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($this->isPrivileged($user)) {
            $this->userVisibilityService->applyToUserOwnedQuery($query, $user);
        } else {
            $query->where('user_id', $user->id);
        }

        $query->when($request->filled('category'), fn ($builder) => $builder->where('category', $request->input('category')));
        $query->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->input('status')));

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        [$sortField, $sortDirection] = $this->parseSort($request->input('sort'));
        $query->orderBy($sortField, $sortDirection);

        $perPage = max(1, min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));
        $documents = $query->paginate($perPage)->appends($request->query());

        return DocumentResource::collection($documents);
    }

    public function store(DocumentStoreRequest $request)
    {
        $user = $request->user();
        $created = [];
        $disk = 's3';

        foreach ($request->file('files', []) as $file) {
            [$path, $extension, $mime] = $this->uploadPrivateEmployeeDocumentToS3(
                $file,
                $user->company_id,
                $user->id,
            );

            try {
                $document = Document::create([
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'title' => $this->resolveTitle($file->getClientOriginalName(), $request->input('title')),
                    'category' => $request->input('category'),
                    'status' => Document::STATUS_PENDING,
                    'mime_type' => $mime,
                    'ext' => $extension,
                    'size_bytes' => $file->getSize() ?: 0,
                    'path' => $path,
                    'storage_disk' => $disk,
                    'original_name' => $file->getClientOriginalName(),
                    'uploaded_by' => $user->id,
                    'notes' => $request->input('notes'),
                ]);
            } catch (Throwable $exception) {
                Storage::disk($disk)->delete($path);
                throw $exception;
            }

            $this->logDocumentAudit($document, 'upload', [
                'original_name' => $file->getClientOriginalName(),
            ]);

            $created[] = $document;
        }

        $resource = DocumentResource::collection(collect($created))->response();

        return $resource->setStatusCode(201);
    }

    public function show(Document $document)
    {
        $this->authorize('view', $document);

        return new DocumentResource($document);
    }

    public function view(Document $document)
    {
        $this->authorize('view', $document);

        $disk = Storage::disk($document->storage_disk ?? Document::STORAGE_DISK);
        $path = $this->resolveDocumentPath($document, $disk);

        if (! $path || ! $disk->exists($path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        $this->logDocumentAudit($document, 'view');

        $filename = $this->sanitizeFilename($document);
        $headers = [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            'Content-Length' => $document->size_bytes,
            'Content-Disposition' => sprintf('inline; filename="%s"', addslashes($filename)),
            'Cache-Control' => 'no-store',
        ];

        return response()->stream(function () use ($disk, $path) {
            $stream = $disk->readStream($path);

            if (! $stream) {
                abort(404, 'Arquivo não encontrado.');
            }

            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, $headers);
    }

    public function download(Document $document)
    {
        $this->authorize('download', $document);

        $diskName = $document->storage_disk ?? Document::STORAGE_DISK;
        $disk = Storage::disk($diskName);
        $path = $this->resolveDocumentPath($document, $disk);

        if (! $path || ! $disk->exists($path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        $this->logDocumentAudit($document, 'download');

        if ($diskName === 's3' && method_exists($disk, 'temporaryUrl')) {
            $url = $disk->temporaryUrl($path, now()->addMinutes(5), [
                'ResponseContentDisposition' => sprintf(
                    'attachment; filename="%s"',
                    addslashes($this->sanitizeFilename($document)),
                ),
                'ResponseContentType' => $document->mime_type ?? 'application/octet-stream',
            ]);

            return redirect()->away($url);
        }

        return $disk->download($path, $this->sanitizeFilename($document), [
            'Cache-Control' => 'no-store',
        ]);
    }

    public function update(DocumentUpdateRequest $request, Document $document)
    {
        $this->authorize('update', $document);

        $oldStatus = $document->status;
        $document->fill($request->only(['title', 'category', 'status', 'notes']));
        $document->save();

        if ($request->filled('status') && $request->input('status') !== $oldStatus) {
            $this->logDocumentAudit($document, 'status_change', [
                'from' => $oldStatus,
                'to' => $document->status,
            ]);
        }

        return new DocumentResource($document->fresh());
    }

    public function approve(Document $document)
    {
        $this->authorize('update', $document);

        if ($document->status !== Document::STATUS_AVAILABLE) {
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
        }

        return new DocumentResource($document->fresh());
    }

    public function destroy(Document $document)
    {
        $this->authorize('delete', $document);

        $disk = Storage::disk($document->storage_disk ?? Document::STORAGE_DISK);
        $path = $this->resolveDocumentPath($document, $disk);

        if ($path && $disk->exists($path)) {
            $disk->delete($path);
        }

        $this->logDocumentAudit($document, 'delete');

        $document->delete();

        return response()->noContent();
    }

    public function resend(DocumentResendRequest $request, Document $document)
    {
        $this->authorize('resend', $document);

        if ($document->status !== Document::STATUS_REVIEW) {
            return response()->json(['message' => 'Documento não está em revisão'], 422);
        }

        $currentDisk = Storage::disk($document->storage_disk ?? Document::STORAGE_DISK);
        $resolved = $this->resolveDocumentPath($document, $currentDisk);

        if ($resolved && $currentDisk->exists($resolved)) {
            $currentDisk->delete($resolved);
        }

        $file = $request->file('file');
        $disk = 's3';
        [$path, $extension, $mime] = $this->uploadPrivateEmployeeDocumentToS3(
            $file,
            $document->company_id,
            $document->user_id,
        );

        try {
            $document->fill([
                'mime_type' => $mime,
                'ext' => $extension,
                'size_bytes' => $file->getSize() ?: 0,
                'path' => $path,
                'storage_disk' => $disk,
                'original_name' => $file->getClientOriginalName(),
                'uploaded_by' => $request->user()->id,
                'status' => Document::STATUS_PENDING,
                'rejected_comment' => null,
                'rejected_by' => null,
                'rejected_at' => null,
            ]);

            $document->save();
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }

        $this->logDocumentAudit($document, 'resend');
        $this->logDocumentAudit($document, 'status_change', [
            'from' => Document::STATUS_REVIEW,
            'to' => Document::STATUS_PENDING,
        ]);

        return new DocumentResource($document->fresh());
    }

    private function parseSort(?string $sort): array
    {
        $sort = $sort ?: sprintf('%s:%s', self::DEFAULT_SORT_FIELD, self::DEFAULT_SORT_DIRECTION);
        [$field, $direction] = array_pad(explode(':', $sort, 2), 2, '');

        $field = in_array($field, self::SORT_FIELDS, true) ? $field : self::DEFAULT_SORT_FIELD;
        $direction = in_array(strtolower($direction), ['asc', 'desc'], true)
            ? strtolower($direction)
            : self::DEFAULT_SORT_DIRECTION;

        return [$field, $direction];
    }

    private function resolveTitle(string $originalName, ?string $prefix): string
    {
        $title = trim($prefix ? sprintf('%s - %s', $prefix, $originalName) : $originalName);

        return Str::limit($title, 180);
    }

    private function sanitizeFilename(Document $document): string
    {
        $filename = $document->title ?? 'document';
        $clean = preg_replace('/[^A-Za-z0-9\.\-_ ]+/', '_', $filename);

        return Str::limit($clean, 120, '');
    }

    private function resolveDocumentPath(Document $document, $disk = null): ?string
    {
        $disk = $disk ?? Storage::disk($document->storage_disk ?? Document::STORAGE_DISK);

        if ($disk->exists($document->path)) {
            return $document->path;
        }

        $fallback = sprintf(
            'private/documents/%s/%s/%s',
            $document->company_id,
            $document->user_id,
            basename($document->path),
        );

        if ($disk->exists($fallback)) {
            $document->forceFill(['path' => $fallback])->saveQuietly();

            return $fallback;
        }

        return null;
    }

    private function uploadPrivateEmployeeDocumentToS3(UploadedFile $file, string $companyId, string $employeeId): array
    {
        $id = (string) Str::ulid();
        $rawExtension = Str::lower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin'));
        $extension = preg_replace('/[^a-z0-9]+/', '', $rawExtension) ?: 'bin';
        $path = sprintf(
            'companies/%s/employees/%s/documents/%s.%s',
            $companyId,
            $employeeId,
            $id,
            $extension,
        );

        $stream = fopen($file->getRealPath(), 'rb');
        $uploaded = $stream
            ? Storage::disk('s3')->put($path, $stream, [
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

        return [
            $path,
            $extension,
            $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
        ];
    }

    private function isPrivileged(User $user): bool
    {
        return $user->hasRole(self::PRIVILEGED_ROLES);
    }
}
