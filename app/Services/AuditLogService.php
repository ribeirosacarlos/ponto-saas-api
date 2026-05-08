<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

class AuditLogService
{
    public function log(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?string $companyId = null,
        ?string $targetCompanyId = null,
        ?string $userId = null,
        ?string $performedByRole = null
    ): ?AuditLog {
        if ($entityType === AuditLog::class || $entityType === 'audit_log') {
            return null;
        }

        try {
            $request = $this->request();
            $actor = $this->resolveActor();

            $companyId ??= $actor?->company_id ?? app(TenantManager::class)->id();
            $userId ??= $actor?->id;
            $performedByRole ??= $this->resolveRole($actor);

            $enrichedMetadata = array_merge(
                $metadata ?? [],
                ['device_type' => $this->detectDeviceType($request?->userAgent())]
            );

            return AuditLog::create([
                'company_id' => $companyId,
                'target_company_id' => $targetCompanyId,
                'user_id' => $userId,
                'performed_by_role' => $performedByRole,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'old_values' => $this->sanitizePayload($oldValues),
                'new_values' => $this->sanitizePayload($newValues),
                'metadata' => $this->sanitizePayload($enrichedMetadata),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'method' => $request?->method(),
                'route' => $request?->route()?->uri() ?? $request?->path(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function snapshot(Model|array $source, array $only = []): array
    {
        $payload = AuditSanitizer::sanitize($source);

        if ($only === []) {
            return $payload;
        }

        return collect($payload)
            ->only($only)
            ->all();
    }

    public function diff(array $before, array $after): array
    {
        return AuditSanitizer::diff(
            AuditSanitizer::sanitize($before),
            AuditSanitizer::sanitize($after),
        );
    }

    private function resolveActor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function resolveRole(?User $actor): ?string
    {
        if (! $actor) {
            return null;
        }

        return $actor->relationLoaded('roles')
            ? $actor->roles->pluck('name')->first()
            : $actor->roles()->pluck('name')->first();
    }

    private function request(): ?Request
    {
        return app()->bound('request') ? request() : null;
    }

    private function detectDeviceType(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'unknown';
        }

        $mobilePattern = '/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Windows Phone/i';

        return preg_match($mobilePattern, $userAgent) ? 'mobile' : 'desktop';
    }

    private function sanitizePayload(?array $payload): ?array
    {
        if ($payload === null || $payload === []) {
            return null;
        }

        return AuditSanitizer::sanitize($payload);
    }
}
