<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformCompanyStoreRequest;
use App\Http\Requests\PlatformCompanyUpdateRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\AuditLogService;
use App\Services\CompanySlugService;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    private CompanySlugService $slugService;

    public function __construct(
        CompanySlugService $slugService,
        protected AuditLogService $auditLogService
    )
    {
        $this->slugService = $slugService;
    }

    public function index(Request $request)
    {
        $status = strtolower($request->input('status', ''));

        $query = Company::with('subscription.plan');

        if ($status === 'deleted') {
            $query = $query->onlyTrashed();
        } else {
            if ($status === 'blocked') {
                $query->where('is_blocked', true);
            } elseif ($status === 'active') {
                $query->where('is_blocked', false);
            }
        }

        $search = $request->input('search');
        if ($search) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'name');
        $direction = 'asc';

        if (str_starts_with($sort, '-')) {
            $direction = 'desc';
            $sort = ltrim($sort, '-');
        }

        $allowedSorts = ['name', 'slug', 'created_at', 'updated_at'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $perPage = max(1, min((int) $request->input('per_page', 20), 100));

        $companies = $query->orderBy($sort, $direction)->paginate($perPage);

        return CompanyResource::collection($companies);
    }

    public function store(PlatformCompanyStoreRequest $request)
    {
        $payload = $request->validated();
        $payload['slug'] = $this->slugService->generate($payload['name']);

        $company = Company::create($payload);

        $this->auditLogService->log(
            action: 'platform.company_created',
            entityType: Company::class,
            entityId: $company->id,
            description: 'Empresa criada pela plataforma.',
            newValues: $this->companySnapshot($company),
            targetCompanyId: $company->id,
        );

        return (new CompanyResource($company))->response()->setStatusCode(201);
    }

    public function show(string $company)
    {
        $company = $this->findWithTrashed($company);

        return new CompanyResource($company);
    }

    public function update(PlatformCompanyUpdateRequest $request, string $company)
    {
        $company = $this->findWithTrashed($company);

        if ($company->trashed()) {
            abort(404);
        }

        $before = $this->companySnapshot($company);
        $payload = $request->validated();

        if (array_key_exists('name', $payload)) {
            $payload['slug'] = $this->slugService->generate($payload['name'], $company->id);
        }

        $company->update($payload);

        [$oldValues, $newValues] = $this->auditLogService->diff($before, $this->companySnapshot($company->fresh()));

        if ($oldValues !== [] || $newValues !== []) {
            $this->auditLogService->log(
                action: 'platform.company_updated',
                entityType: Company::class,
                entityId: $company->id,
                description: 'Dados da empresa alterados por super admin.',
                oldValues: $oldValues,
                newValues: $newValues,
                targetCompanyId: $company->id,
            );
        }

        return new CompanyResource($company);
    }

    public function destroy(string $company)
    {
        $company = Company::with('subscription.plan')->findOrFail($company);
        $snapshot = $this->companySnapshot($company);
        $company->delete();

        $this->auditLogService->log(
            action: 'platform.company_deleted',
            entityType: Company::class,
            entityId: $company->id,
            description: 'Empresa removida da plataforma.',
            oldValues: $snapshot,
            targetCompanyId: $company->id,
        );

        return response()->noContent();
    }

    public function restore(string $company)
    {
        $company = $this->findWithTrashed($company);

        if (! $company->trashed()) {
            return response()->json(['message' => 'Empresa não está deletada.'], 422);
        }

        $company->restore();

        $this->auditLogService->log(
            action: 'platform.company_restored',
            entityType: Company::class,
            entityId: $company->id,
            description: 'Empresa restaurada na plataforma.',
            newValues: $this->companySnapshot($company->fresh()),
            targetCompanyId: $company->id,
        );

        return new CompanyResource($company);
    }

    public function block(Request $request, string $company)
    {
        $payload = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $company = Company::with('subscription.plan')->findOrFail($company);

        if ($company->is_blocked) {
            return new CompanyResource($company);
        }

        $before = $this->auditLogService->snapshot([
            'is_blocked' => (bool) $company->is_blocked,
            'blocked_at' => optional($company->blocked_at)->toIso8601String(),
            'blocked_reason' => $company->blocked_reason,
        ]);

        $company->update([
            'is_blocked' => true,
            'blocked_at' => now(),
            'blocked_reason' => $payload['reason'] ?? null,
        ]);

        $this->auditLogService->log(
            action: 'platform.company_blocked',
            entityType: Company::class,
            entityId: $company->id,
            description: 'Empresa bloqueada por super admin.',
            oldValues: $before,
            newValues: $this->auditLogService->snapshot([
                'is_blocked' => (bool) $company->fresh()->is_blocked,
                'blocked_at' => optional($company->blocked_at)->toIso8601String(),
                'blocked_reason' => $company->blocked_reason,
            ]),
            targetCompanyId: $company->id,
        );

        return new CompanyResource($company);
    }

    public function unblock(string $company)
    {
        $company = Company::findOrFail($company);

        if (! $company->is_blocked) {
            return new CompanyResource($company);
        }

        $before = $this->auditLogService->snapshot([
            'is_blocked' => (bool) $company->is_blocked,
            'blocked_at' => optional($company->blocked_at)->toIso8601String(),
            'blocked_reason' => $company->blocked_reason,
        ]);

        $company->update([
            'is_blocked' => false,
            'blocked_at' => null,
            'blocked_reason' => null,
        ]);

        $this->auditLogService->log(
            action: 'platform.company_unblocked',
            entityType: Company::class,
            entityId: $company->id,
            description: 'Empresa desbloqueada por super admin.',
            oldValues: $before,
            newValues: $this->auditLogService->snapshot([
                'is_blocked' => (bool) $company->fresh()->is_blocked,
                'blocked_at' => optional($company->blocked_at)->toIso8601String(),
                'blocked_reason' => $company->blocked_reason,
            ]),
            targetCompanyId: $company->id,
        );

        return new CompanyResource($company);
    }

    protected function findWithTrashed(string $id): Company
    {
        return Company::withTrashed()->with('subscription.plan')->findOrFail($id);
    }

    protected function companySnapshot(Company $company): array
    {
        return $this->auditLogService->snapshot([
            'name' => $company->name,
            'slug' => $company->slug,
            'document' => $company->document,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address,
            'city' => $company->city,
            'state' => $company->state,
            'is_blocked' => (bool) $company->is_blocked,
            'blocked_reason' => $company->blocked_reason,
            'deleted_at' => optional($company->deleted_at)->toIso8601String(),
        ]);
    }

}
