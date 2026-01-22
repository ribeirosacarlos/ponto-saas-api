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
use Illuminate\Http\Request;
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

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Document::query();

        if ($this->isPrivileged($user) && $request->filled('user_id')) {
            $targetUser = User::where('id', $request->input('user_id'))
                ->where('company_id', $user->company_id)
                ->first();

            if ($targetUser) {
                $query->where('user_id', $targetUser->id);
            } else {
                $query->where('user_id', $user->id);
            }
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

        foreach ($request->file('files', []) as $file) {
            $directory = sprintf('private/documents/%s/%s', $user->company_id, $user->id);
            $filename = sprintf('%s.%s', Str::random(16), Str::lower($file->getClientOriginalExtension()));
            $path = Storage::disk(Document::STORAGE_DISK)->putFileAs($directory, $filename, $file);

            if (! $path) {
                throw new RuntimeException('Não foi possível salvar o arquivo.');
            }

            try {
                $document = Document::create([
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'title' => $this->resolveTitle($file->getClientOriginalName(), $request->input('title')),
                    'category' => $request->input('category'),
                    'status' => Document::STATUS_PENDING,
                    'mime_type' => $file->getClientMimeType(),
                    'ext' => Str::lower($file->getClientOriginalExtension()),
                    'size_bytes' => $file->getSize() ?: 0,
                    'path' => $path,
                    'storage_disk' => Document::STORAGE_DISK,
                    'notes' => $request->input('notes'),
                ]);
            } catch (Throwable $exception) {
                Storage::disk(Document::STORAGE_DISK)->delete($path);
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

        $disk = Storage::disk(Document::STORAGE_DISK);
        $path = $this->resolveDocumentPath($document);

        if (! $path || ! $disk->exists($path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        $this->logDocumentAudit($document, 'view');

        $filename = $this->sanitizeFilename($document);
        $headers = [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            'Content-Length' => $document->size_bytes,
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
        ];

        return response()->stream(function () use ($path) {
            $stream = Storage::disk(Document::STORAGE_DISK)->readStream($path);

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

        $disk = Storage::disk(Document::STORAGE_DISK);
        $path = $this->resolveDocumentPath($document);

        if (! $path || ! $disk->exists($path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        $this->logDocumentAudit($document, 'download');

        $headers = [
            'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            'Content-Length' => $document->size_bytes,
        ];

        return response()->streamDownload(function () use ($path) {
            $stream = Storage::disk(Document::STORAGE_DISK)->readStream($path);

            if (! $stream) {
                abort(404, 'Arquivo não encontrado.');
            }

            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, $this->sanitizeFilename($document), $headers);
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

        $disk = Storage::disk(Document::STORAGE_DISK);

        $path = $this->resolveDocumentPath($document);

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

        $disk = Storage::disk(Document::STORAGE_DISK);
        $resolved = $this->resolveDocumentPath($document);

        if ($resolved && $disk->exists($resolved)) {
            $disk->delete($resolved);
        }

        $file = $request->file('file');
        $directory = sprintf('private/documents/%s/%s', $document->company_id, $document->user_id);
        $filename = sprintf('%s.%s', Str::random(16), Str::lower($file->getClientOriginalExtension()));
        $path = $disk->putFileAs($directory, $filename, $file);

        if (! $path) {
            throw new RuntimeException('Não foi possível salvar o arquivo.');
        }

        $document->fill([
            'mime_type' => $file->getClientMimeType(),
            'ext' => Str::lower($file->getClientOriginalExtension()),
            'size_bytes' => $file->getSize() ?: 0,
            'path' => $path,
            'status' => Document::STATUS_PENDING,
            'rejected_comment' => null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);

        $document->save();

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

    private function resolveDocumentPath(Document $document): ?string
    {
        $disk = Storage::disk(Document::STORAGE_DISK);

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

    private function isPrivileged(User $user): bool
    {
        return $user->hasRole(self::PRIVILEGED_ROLES);
    }

}
