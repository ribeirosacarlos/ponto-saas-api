<?php

namespace App\Actions\Platform;

use App\Models\Company;
use App\Services\AuditLogService;

class UpdateCompanyAdminSettingsAction
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {
    }

    public function execute(Company $company, array $payload): Company
    {
        $before = $this->settingsSnapshot($company);

        $company->update($payload);

        $company = $company->fresh();
        $after = $this->settingsSnapshot($company);
        [$oldValues, $newValues] = $this->auditLogService->diff($before, $after);

        if ($oldValues === [] && $newValues === []) {
            return $company;
        }

        $this->auditLogService->log(
            action: 'platform.company_settings_updated',
            entityType: Company::class,
            entityId: $company->id,
            description: 'Configurações administrativas da empresa alteradas por super admin.',
            oldValues: $oldValues,
            newValues: $newValues,
            targetCompanyId: $company->id,
            metadata: [
                'changed_fields' => array_keys($newValues),
            ],
        );

        if (array_key_exists('timezone', $payload) && $before['timezone'] !== $after['timezone']) {
            $this->auditLogService->log(
                action: 'platform.company_timezone_updated',
                entityType: Company::class,
                entityId: $company->id,
                description: 'Timezone da empresa alterada por super admin.',
                oldValues: ['timezone' => $before['timezone']],
                newValues: ['timezone' => $after['timezone']],
                targetCompanyId: $company->id,
            );
        }

        if (
            array_key_exists('audit_logs_enabled', $payload)
            && $before['audit_logs_enabled'] !== $after['audit_logs_enabled']
        ) {
            $this->auditLogService->log(
                action: $after['audit_logs_enabled']
                    ? 'platform.company_audit_logs_enabled'
                    : 'platform.company_audit_logs_disabled',
                entityType: Company::class,
                entityId: $company->id,
                description: $after['audit_logs_enabled']
                    ? 'Visualização de auditoria habilitada para a empresa.'
                    : 'Visualização de auditoria desabilitada para a empresa.',
                oldValues: ['audit_logs_enabled' => $before['audit_logs_enabled']],
                newValues: ['audit_logs_enabled' => $after['audit_logs_enabled']],
                targetCompanyId: $company->id,
            );
        }

        return $company;
    }

    private function settingsSnapshot(Company $company): array
    {
        return $this->auditLogService->snapshot([
            'timezone' => $company->timezone,
            'audit_logs_enabled' => (bool) $company->audit_logs_enabled,
        ]);
    }
}
