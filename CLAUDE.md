# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Setup completo
composer run setup        # install + .env + key:generate + migrate + npm install + build

# Desenvolvimento (todos os processos juntos)
composer run dev          # artisan serve + queue:listen + pail (logs) + npm dev

# Testes
composer run test         # config:clear + phpunit
php artisan test --filter NomeDoTeste   # teste único
php artisan test tests/Feature/TimeEntryTest.php  # arquivo específico

# Migrations
php artisan migrate
php artisan migrate:fresh --seed

# Queue worker
php artisan queue:work database --queue=default --sleep=1 --tries=3 --timeout=120

# Code style
./vendor/bin/pint          # Laravel Pint (fixer)

# API Docs (Swagger/OpenAPI)
php artisan l5-swagger:generate
```

## Arquitetura

### Multi-tenancy

Todos os models de negócio têm `company_id`. A trait `CompanyScoped` (`app/Traits/CompanyScoped.php`) aplica um global scope que filtra automaticamente por `company_id` baseado no usuário autenticado, via `TenantManager`. Ao criar models novos que pertencem a uma empresa, incluir essa trait. A trait `HasUuid` é usada em todos os models — PKs são UUIDs, nunca auto-increment.

Regras obrigatórias para endpoints multi-tenant:
- Roles não substituem isolamento de tenant. Toda listagem deve filtrar explicitamente por `company_id` do usuário autenticado ou usar helper equivalente que aplique esse filtro.
- Toda policy ou ação por ID deve comparar `user.company_id` com o `company_id` do recurso antes de liberar acesso.
- Rotas aninhadas devem validar o relacionamento entre os modelos da URL antes de executar ações.
- Recursos associados a colaboradores devem respeitar `UserVisibilityService` para `manager` e `area_manager`.
- Toda implementação ou revisão de endpoint com dados de empresa deve incluir teste com duas empresas quando houver risco de vazamento cross-company.

### RBAC e Autorização

Cinco roles: `employee`, `manager`, `area_manager`, `admin`, `super_admin`. Armazenados na tabela `roles` com pivot `role_user`.

**Middleware** (em `app/Http/Middleware/`):
- `RoleMiddleware` — sintaxe `role:admin|manager` nas rotas
- `EnsureCompanyHasAccess` — valida status da subscription
- `EnsurePlanFeature` — feature flags por plano
- `EnsureCompanyAuditLogsEnabled` — para endpoints de audit log
- `SetCompanyTimezone` — resolve timezone da empresa para a request

**Policies** (em `app/Policies/`) são usadas via `$this->authorize('action', $model)` nos controllers. `UserVisibilityService` é a peça central para checar se um manager/area_manager pode ver/modificar registros de um employee específico — sempre usar esse service em vez de lógica inline.

### Rotas

`routes/api.php` inclui os arquivos em `routes/api/v1/`. Estrutura:
- `public.php` — registro, login (sem auth)
- `auth.php` — endpoints comuns autenticados
- `admin.php` — endpoints de administrador (maior arquivo)
- `area-manager.php` — endpoints de gerente de área
- `employee.php` — endpoints de employee
- `platform.php` — endpoints de plataforma (super admin)
- `documents.php` — gestão de documentos

Controllers ficam em `app/Http/Controllers/Api/` organizados por role (`Admin/`, `AreaManager/`, `Employee/`, `Platform/`, `SuperAdmin/`).

### Services e Actions

Lógica de negócio complexa vai em `app/Services/`. Actions simples de um único passo ficam em `app/Actions/`. Principais services:

- `StripeBillingService` — integração completa Stripe (checkout, webhooks, overages por employee)
- `CompanySubscriptionService` — status de acesso e expiração
- `AuditLogService` — registrar ações com valores antigos/novos
- `VacationAccrualService` / `VacationBalanceService` / `VacationConsumptionService` — sistema de férias/ausências
- `UserVisibilityService` — controle de visibilidade entre roles
- `UserShiftResolver` — resolução de qual turno se aplica a um usuário

### Audit Logging

Toda ação relevante deve ser registrada via `AuditLogService::log()`. O middleware `EnsureCompanyAuditLogsEnabled` protege as rotas de listagem dos logs. A feature é habilitada por empresa via `companies.audit_logs_enabled`.

### Billing / Stripe

Modelo de cobrança: preço base do plano + cobrança por employee adicional acima do limite. `ExtraEmployeeChargeService` lida com os overages. Webhooks Stripe entram em `routes/api/v1/public.php`. Status da subscription é um enum `SubscriptionStatus` (TRIALING, ACTIVE, PAST_DUE, CANCELED).

### Jobs e Emails

Emails são disparados via jobs enfileirados (nunca diretamente): `SendEmployeeInviteJob`, `SendCompanyAdminInviteJob`, `SendPasswordResetEmailJob`. Provider de email: Resend (`RESEND_API_KEY`). Queue driver: database.

### Testes

Testes Feature em `tests/Feature/` usam SQLite in-memory. Exemplos de referência: `TimeEntryTest.php`, `BillingExtraEmployeesTest.php`, `StripeSubscriptionSyncTest.php`.

### Docker / Deploy

Três containers: `app` (Laravel), `queue-worker` (queue:work), `nginx` (proxy). Imagens no ECR, deploy via GitHub Actions (`.github/workflows/deploy.yml`). Logs via CloudWatch.
