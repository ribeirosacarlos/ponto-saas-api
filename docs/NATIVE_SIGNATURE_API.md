# Assinatura Nativa da Folha de Ponto

Módulo de assinatura eletrônica simples integrado ao Jornafy, sem dependência de serviço externo. Gera evidências técnicas suficientes para auditoria e produz um PDF assinado com rastreabilidade completa.

---

## Regras de Negócio

### Fluxo de assinatura

1. O admin cria um fechamento mensal (`MonthlyClosure`). Uma `EmployeeTimesheet` é gerada por colaborador com status `pending_employee`.
2. O **colaborador** assina sua própria folha → status avança para `pending_manager`.
3. O **gestor/admin** assina em seguida → status avança para `completed` e o PDF é gerado automaticamente.
4. Quando todos os colaboradores do fechamento estiverem em `completed`, o `MonthlyClosure` avança para `completed`.

### Assinatura do colaborador — o que é validado

| Campo | Obrigatório | Regra |
|-------|-------------|-------|
| `signature_image` | Sim | Base64 da assinatura desenhada em canvas. Máximo ~750 KB após decode. |
| `accepted_terms` | Sim | Deve ser `true`. Aceite explícito da declaração de responsabilidade. |
| `password` | Sim | Senha atual do colaborador. Verificada com `Hash::check`. |
| `latitude` | Não | Decimal entre -90 e 90. Enviado se o dispositivo permitir. |
| `longitude` | Não | Decimal entre -180 e 180. Enviado se o dispositivo permitir. |

### Assinatura do gestor

O gestor assina via endpoint admin. Não requer canvas nem senha — apenas autenticação e permissão de visibilidade sobre o colaborador.

### Bloqueios

- Colaborador não pode assinar folha de outro colaborador (403).
- Colaborador não pode assinar enquanto houver disputa aberta (422 `dispute`).
- Colaborador não pode assinar se o status não for `pending_employee` (422 `status`).
- Gestor não pode assinar se o status não for `pending_manager` (422 `status`).
- Gestor não pode assinar folha de colaborador fora do seu escopo de visibilidade (403).

### Superseded — invalidação automática de assinaturas

Se o snapshot da folha for regenerado **após** uma assinatura existente (ex: admin ajusta um ponto do período fechado), o sistema:

1. Marca todas as assinaturas ativas como `superseded_at = now()`.
2. Reseta o status da folha para `pending_employee`.
3. Limpa o `pdf_path`, `pdf_generated_at` e `document_hash`.
4. Registra evento de auditoria `timesheet.signatures_superseded`.

O colaborador precisa assinar novamente. O histórico das assinaturas antigas é preservado no banco (campo `superseded_at` preenchido).

### PDF assinado

Gerado automaticamente após a assinatura do gestor. Contém:

- Dados do colaborador, empresa e período
- Tabela de registros diários com entradas/saídas e horas trabalhadas
- Totais do período
- Texto de declaração: *"Declaro que visualizei e confirmei eletronicamente esta folha de ponto, reconhecendo os registros apresentados para o período indicado."*
- Imagem da assinatura canvas do colaborador
- Evidências técnicas: data/hora UTC, IP, geolocalização, hash do documento, hash da assinatura, aceite de termos
- Dados da assinatura do gestor

Armazenado em `timesheets/signed/{company_id}/{timesheet_id}.pdf` no disco configurado (`local` ou `s3`).

> O caminho acima é exclusivamente interno. A API não retorna mais `pdf_path`; clientes usam `pdf_available` e o endpoint autenticado `/timesheets/{timesheet}/pdf`.

### Configurações por empresa (`companies`)

| Campo | Tipo | Padrão | Descrição |
|-------|------|--------|-----------|
| `enable_native_signatures` | boolean | `false` | Habilita o módulo de assinatura nativa |
| `require_timesheet_signature` | boolean | `false` | Exige assinatura para concluir a folha |
| `require_password_confirmation_for_signature` | boolean | `true` | Exige senha ao assinar |
| `allow_geolocation_on_signature` | boolean | `false` | Permite capturar geolocalização ao assinar |

> **Atenção:** as configurações acima estão no banco mas ainda não são usadas como guard nas rotas — são voltadas para o frontend controlar a UI. A validação de senha é sempre aplicada no backend independentemente de `require_password_confirmation_for_signature`.

---

## Evidências de Auditoria

Cada assinatura registra na tabela `timesheet_signatures`:

| Campo | Descrição |
|-------|-----------|
| `ip_address` | IP do cliente no momento da assinatura |
| `user_agent` | User agent do navegador/app |
| `signature_image_path` | Caminho da imagem canvas no Storage |
| `document_hash` | SHA256 do JSON do snapshot da folha |
| `signature_hash` | SHA256 composto de path + document_hash + signer_id + timestamp |
| `latitude` / `longitude` | Geolocalização, se enviada |
| `accepted_terms` | Booleano — sempre `true` ao assinar |
| `password_confirmed_at` | Timestamp da confirmação de senha |
| `superseded_at` | Preenchido quando a assinatura é invalidada por alteração no documento |
| `metadata` | JSON com informações extras (ex: device_type) |

Eventos registrados no `AuditLog`:

| Action | Quando |
|--------|--------|
| `timesheet.signed_by_employee` | Colaborador assina |
| `timesheet.signed_by_manager` | Gestor assina |
| `timesheet.signatures_superseded` | Assinaturas invalidadas por alteração |

---

## Endpoints

### Colaborador

#### `POST /api/v1/employee/timesheets/{timesheet}/sign`

Assina a folha de ponto como colaborador.

**Autenticação:** Bearer token (Sanctum) — role `employee`

**Body:**
```json
{
  "signature_image": "data:image/png;base64,iVBORw0KGgo...",
  "accepted_terms": true,
  "password": "senha-atual",
  "latitude": -23.5505,
  "longitude": -46.6333
}
```

**Respostas:**
- `200 OK` — folha assinada, retorna `EmployeeTimesheetResource`
- `403 Forbidden` — tentativa de assinar folha de outro colaborador
- `422 Unprocessable` — campos inválidos, senha errada, disputa aberta, status incorreto

---

#### `GET /api/v1/employee/timesheets/{timesheet}/pdf`

Download do PDF assinado da própria folha.

**Autenticação:** Bearer token — role `employee`

**Respostas:**
- `200 OK` — arquivo PDF (disco local) ou `{ "url": "..." }` (S3 com URL temporária de 30 min)
- `404 Not Found` — PDF ainda não gerado

---

### Admin / Gestor

#### `POST /api/v1/admin/timesheets/{timesheet}/sign`

Assina a folha de ponto como gestor, concluindo o ciclo de assinatura e gerando o PDF.

**Autenticação:** Bearer token — role `admin | manager | area_manager`

**Body:** sem campos obrigatórios (request vazio aceito)

**Respostas:**
- `200 OK` — folha assinada e PDF gerado, retorna `EmployeeTimesheetResource`
- `403 Forbidden` — colaborador fora do escopo de visibilidade do gestor
- `422 Unprocessable` — status incorreto (colaborador ainda não assinou)

---

#### `GET /api/v1/admin/timesheets/{timesheet}/pdf`

Download do PDF assinado de qualquer folha da empresa.

**Autenticação:** Bearer token — role `admin | manager | area_manager`

**Respostas:**
- `200 OK` — arquivo PDF ou `{ "url": "..." }` (S3)
- `404 Not Found` — PDF não gerado

---

## Estrutura das tabelas

### `timesheet_signatures` (campos adicionados)

```
signature_image_path  varchar(500)   nullable  — path da imagem no Storage
document_hash         varchar(64)    nullable  — SHA256 do snapshot JSON
signature_hash        varchar(64)    nullable  — SHA256 da composição de evidências
latitude              decimal(10,7)  nullable
longitude             decimal(10,7)  nullable
accepted_terms        boolean        default false
password_confirmed_at timestamp      nullable
superseded_at         timestamp      nullable  — preenchido quando invalidada
metadata              json           nullable
```

### `employee_timesheets` (campo adicionado)

```
document_hash  varchar(64)  nullable  — SHA256 do snapshot no momento da assinatura
```

### `companies` (campos adicionados)

```
enable_native_signatures                    boolean  default false
require_timesheet_signature                 boolean  default false
require_password_confirmation_for_signature boolean  default true
allow_geolocation_on_signature              boolean  default false
```

---

## Arquivos relevantes

| Arquivo | Responsabilidade |
|---------|-----------------|
| `app/Services/Timesheet/TimesheetSignatureService.php` | Lógica central: valida, salva imagem, calcula hashes, supersede |
| `app/Services/Timesheet/TimesheetPdfService.php` | Geração e armazenamento do PDF assinado |
| `app/Services/Timesheet/TimesheetSnapshotService.php` | Chama `supersedePreviousSignatures` antes de salvar novo snapshot |
| `app/Http/Requests/SignTimesheetRequest.php` | Validação dos campos do colaborador |
| `app/Http/Requests/AdminSignTimesheetRequest.php` | Request do gestor (sem campos obrigatórios) |
| `resources/views/pdf/timesheet_signature.blade.php` | Template Blade do PDF |
| `app/Models/TimesheetSignature.php` | Model com todos os campos de evidência |
| `app/Models/EmployeeTimesheet.php` | `computeDocumentHash()`, `hasSignatureChanged()`, `activeSignatures()` |
| `tests/Feature/Timesheet/TimesheetNativeSignatureTest.php` | Testes do módulo nativo |
