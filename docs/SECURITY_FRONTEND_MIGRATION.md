# Migração do frontend — hardening de segurança v1

Data: 2026-07-02

Esta alteração sanitiza o contrato da API v1. O Swagger/OpenAPI é a fonte de verdade para os payloads atualizados.

## Alterações obrigatórias

| Contrato anterior | Contrato atual | Ação no frontend |
|---|---|---|
| `document.storage_disk` | removido | Não inferir provedor de storage. |
| `document.storage_path` | removido | Usar `view_url`, `download_url` ou `/v1/documents/{id}/view|download`. |
| `timesheet.pdf_path` | removido | Usar `timesheet.pdf_available`. |
| caminho do PDF | endpoint autenticado | Quando `pdf_available === true`, chamar `/v1/employee/timesheets/{id}/pdf` ou `/v1/admin/timesheets/{id}/pdf`. |
| objeto completo de `company` em login/me | resumo allowlist | Buscar billing e configurações nos endpoints `/v1/settings/*`. |
| token sem expiração definida | `expires_in: 2592000` | Tratar 401 como sessão expirada e redirecionar ao login. |

`document_hash` e `signature_hash` continuam disponíveis para validação de integridade; não são credenciais.

## Autenticação

- Enviar o token somente em `Authorization: Bearer <token>`.
- Não persistir token em URL, query string ou logs do navegador.
- O token expira após 30 dias e é revogado no logout, troca de senha ou reset de senha.
- Login e recuperação podem retornar `429`; respeitar `Retry-After` e não repetir automaticamente em loop.

## Checklist do frontend

- [ ] Remover acessos a `storage_disk`, `storage_path` e `pdf_path`.
- [ ] Condicionar ações de PDF a `pdf_available`.
- [ ] Usar os endpoints autenticados de view/download.
- [ ] Aceitar o novo payload allowlist de `/auth/login` e `/auth/me`.
- [ ] Tratar 401 por expiração/revogação e 429 por rate limit.
- [ ] Configurar o origin exato do SPA em `CORS_ALLOWED_ORIGINS` no backend.
- [ ] Regenerar tipos do frontend a partir do OpenAPI atualizado.

## Exemplo de timesheet

```json
{
  "id": "uuid",
  "status": "completed",
  "pdf_available": true,
  "pdf_generated_at": "2026-07-02T10:00:00Z",
  "document_hash": "sha256"
}
```
