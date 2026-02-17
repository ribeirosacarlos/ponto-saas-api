# REPORT.md - Padronização S3 de Documentos de Empregado

## Data
2026-02-17

## Mudanças Implementadas

### 1) Upload de empregado (`POST /api/v1/documents`)
Arquivo: `app/Http/Controllers/Api/Documents/DocumentController.php`

- Fluxo `store()` migrou de `storeAs(..., Document::STORAGE_DISK)` para upload privado em S3.
- Key agora segue padrão multi-tenant:
  - `companies/{company_id}/employees/{employee_id}/documents/{ulid}.{ext}`
- Metadados persistidos:
  - `storage_disk = s3`
  - `path` = key S3
  - `original_name`, `mime_type`, `ext`, `size_bytes`, `uploaded_by`
- Consistência:
  - se persistência no DB falhar após upload, arquivo recém-enviado é removido do S3.

### 2) Reenvio (`POST /api/v1/documents/{document}/resend`)
Arquivo: `app/Http/Controllers/Api/Documents/DocumentController.php`

- Fluxo `resend()` também migrou para upload privado em S3 no mesmo padrão de key.
- Documento anterior continua sendo removido no disco onde estiver (`storage_disk` atual), preservando compatibilidade com legado local.
- Registro atualizado com novos metadados e `storage_disk = s3`.

### 3) Download com URL pré-assinada (`GET /api/v1/documents/{document}/download`)
Arquivo: `app/Http/Controllers/Api/Documents/DocumentController.php`

- Quando `storage_disk = s3`: retorna `302` para URL pré-assinada via `temporaryUrl(..., now()->addMinutes(5))`.
- Quando `storage_disk != s3`: mantém comportamento legado (`$disk->download(...)`).
- `resolveDocumentPath()` foi mantido para preservar leitura de documentos antigos.

## Compatibilidade Legada

- Mantido suporte para documentos antigos em `local`:
  - resolução por `storage_disk` e `path`
  - fallback de `resolveDocumentPath()`
- Não houve remoção de suporte ao disco local para leitura/download legado.

## Testes Adicionados

Arquivo: `tests/Feature/Documents/DocumentS3FlowTest.php`

- `test_employee_store_uploads_document_to_private_s3_and_persists_metadata`
- `test_employee_resend_moves_document_to_s3_and_updates_metadata`
- `test_download_redirects_to_presigned_url_for_s3_documents`
- `test_download_keeps_legacy_behavior_for_local_documents`

Todos os testes usam `Storage::fake('s3')`/`Storage::fake('local')` ou mock de `temporaryUrl`.

## Observação sobre `Document::STORAGE_DISK`

- No modelo, `Document::STORAGE_DISK` permanece `local` para não afetar fallback legado.
- Próximo passo opcional: introduzir disk padrão por configuração/env (ex.: `filesystems.document_upload_disk=s3`) e usar esse valor apenas para novos uploads.
