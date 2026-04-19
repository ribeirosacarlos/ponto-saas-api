<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function companyIndex(AuditLogIndexRequest $request)
    {
        $user = $request->user();

        abort_unless($user && $user->hasRole('admin'), 403, 'Forbidden.');
        abort_if(! $user->company_id, 404, 'Empresa não encontrada.');

        $auditLogs = $this->baseQuery()
            ->where(function (Builder $query) use ($user) {
                $query->where('company_id', $user->company_id)
                    ->orWhere('target_company_id', $user->company_id);
            });

        $this->applyFilters($auditLogs, $request, allowCrossCompanyFilters: false);

        return AuditLogResource::collection(
            $auditLogs->paginate((int) ($request->input('per_page', 20)))
        );
    }

    public function companyShow(Request $request, AuditLog $auditLog): AuditLogResource
    {
        $user = $request->user();

        abort_unless($user && $user->hasRole('admin'), 403, 'Forbidden.');
        abort_if(! $user->company_id, 404, 'Empresa não encontrada.');
        abort_unless(
            (string) $auditLog->company_id === (string) $user->company_id
            || (string) $auditLog->target_company_id === (string) $user->company_id,
            404
        );

        return new AuditLogResource($auditLog->load(['user', 'company', 'targetCompany']));
    }

    public function platformIndex(AuditLogIndexRequest $request)
    {
        $user = $request->user();

        abort_unless($user && $user->hasRole('super_admin'), 403, 'Forbidden.');

        $auditLogs = $this->baseQuery();
        $this->applyFilters($auditLogs, $request, allowCrossCompanyFilters: true);

        return AuditLogResource::collection(
            $auditLogs->paginate((int) ($request->input('per_page', 20)))
        );
    }

    public function platformShow(Request $request, AuditLog $auditLog): AuditLogResource
    {
        $user = $request->user();

        abort_unless($user && $user->hasRole('super_admin'), 403, 'Forbidden.');

        return new AuditLogResource($auditLog->load(['user', 'company', 'targetCompany']));
    }

    private function baseQuery(): Builder
    {
        return AuditLog::query()
            ->with(['user', 'company', 'targetCompany'])
            ->orderByDesc('created_at');
    }

    private function applyFilters(Builder $query, AuditLogIndexRequest $request, bool $allowCrossCompanyFilters): void
    {
        $filters = $request->validated();

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->where(function (Builder $builder) use ($search) {
                $builder->where('action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('entity_type', 'like', "%{$search}%")
                    ->orWhere('entity_id', 'like', "%{$search}%")
                    ->orWhereHas('user', function (Builder $userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('company', function (Builder $companyQuery) use ($search) {
                        $companyQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%");
                    })
                    ->orWhereHas('targetCompany', function (Builder $companyQuery) use ($search) {
                        $companyQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%");
                    });
            });
        }

        foreach (['action', 'entity_type', 'entity_id', 'user_id', 'performed_by_role'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if ($allowCrossCompanyFilters) {
            foreach (['company_id', 'target_company_id'] as $field) {
                if (! empty($filters[$field])) {
                    $query->where($field, $filters[$field]);
                }
            }
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }
    }
}
