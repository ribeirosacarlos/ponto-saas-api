# Story: Exclusão de fechamento mensal sem assinatura

## Status

Ready for Review

## Context

Administradores e gestores precisam excluir um fechamento mensal enquanto nenhuma folha vinculada tiver sido assinada. Assinaturas invalidadas também impedem a exclusão. A operação deve respeitar empresa e áreas gerenciadas e permitir criar novamente o fechamento do período.

## Acceptance Criteria

- [x] Disponibilizar `DELETE /api/v1/admin/monthly-closures/{closure}` com resposta `204`.
- [x] Autorizar somente `admin`, `manager` e `area_manager` da mesma empresa, respeitando visibilidade dos colaboradores para gestores.
- [x] Bloquear com `422` qualquer assinatura vinculada, inclusive invalidada.
- [x] Excluir folhas e contestações vinculadas, preservando batidas e auditoria.
- [x] Permitir fechar novamente o período após exclusão.
- [x] Proteger contra assinatura simultânea e jobs pendentes de geração de snapshot.
- [x] Cobrir isolamento com duas empresas e fechamentos legados com múltiplas folhas.

## Checklist

- [x] Implementação concluída
- [x] Testes de feature e jobs
- [x] Validação de concorrência em PostgreSQL
- [x] Documentação da API atualizada, incluindo integração Axios, atualização de consultas e tratamento de erros para o front-end
- [x] Pint
- [x] Testes de fechamento, assinatura, contestação, abonos e atestados
- [x] `npm run lint`: indisponível, script ausente em `src/package.json`
- [x] `npm run typecheck`: indisponível, script ausente em `src/package.json`
- [x] `npm test`: indisponível, script ausente em `src/package.json`
- [x] `npm run build`: concluído com sucesso

## Validation

- `php artisan test tests/Feature/Timesheet tests/Feature/Jobs/GenerateEmployeeTimesheetJobTest.php tests/Feature/AbsenceAllowanceTest.php tests/Feature/MedicalCertificateTest.php`: 98 testes passaram, 384 assertions; os 2 testes de concorrência são opt-in e foram validados separadamente.
- `MonthlyClosureDeletionTest` em PostgreSQL isolado: 19 testes passaram, 79 assertions.
- `MonthlyClosureConcurrencyTest` em PostgreSQL isolado: 2 testes passaram, 18 assertions. Os testes confirmam que a operação concorrente realmente aguarda um lock em `pg_stat_activity` antes de liberar a transação vencedora.
- Para repetir concorrência: configurar `DB_CONNECTION=pgsql`, credenciais de um banco descartável com nome terminado em `_test` e `TEST_CLOSURE_LOCKS=1`, então executar `php artisan test tests/Feature/Timesheet/MonthlyClosureConcurrencyTest.php`. A classe recria esse banco antes de cada teste; requer `pcntl`.
- Pint aprovado nos 9 arquivos PHP da alteração. Alterações simultâneas em arquivos de cálculo e snapshot fora desta story foram preservadas.
- Sem migration necessária; exclusão utiliza as FKs com cascade existentes.

A story também está versionada em `src/docs/stories/`, dentro da raiz Git do backend.

## File List

- `src/docs/stories/2026-11-10-delete-unsigned-monthly-closure.md`
- `src/app/Http/Controllers/Api/Admin/MonthlyClosureController.php`
- `src/app/Policies/MonthlyClosurePolicy.php`
- `src/app/Services/Timesheet/MonthlyClosureService.php`
- `src/app/Services/Timesheet/TimesheetSignatureService.php`
- `src/app/Jobs/GenerateEmployeeTimesheetJob.php`
- `src/routes/api/v1/admin.php`
- `src/docs/TIMESHEET_CLOSURE_API.md`
- `src/tests/Feature/Timesheet/MonthlyClosureDeletionTest.php`
- `src/tests/Feature/Timesheet/MonthlyClosureConcurrencyTest.php`
- `src/tests/Feature/Jobs/GenerateEmployeeTimesheetJobTest.php`
