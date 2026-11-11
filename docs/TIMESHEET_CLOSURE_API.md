# API — Fechamento Mensal da Folha de Ponto

Documentação completa dos endpoints para integração do frontend com o módulo de fechamento mensal.

---

## Autenticação

Todos os endpoints requerem `Authorization: Bearer {token}` (Sanctum).

## Escopo multi-tenant

- Todos os endpoints de fechamento mensal são escopados pela `company_id` do usuário autenticado.
- `admin`, `manager` e `area_manager` nunca podem listar, detalhar ou alterar fechamentos/timesheets de outra empresa.
- Listagens de timesheets respeitam a visibilidade por colaborador: `admin` vê colaboradores da empresa; `manager` e `area_manager` veem apenas colaboradores das áreas gerenciadas.
- Rotas aninhadas validam os relacionamentos da URL, como fechamento -> timesheets e timesheet -> disputa.

---

## Fluxo Geral

```
Admin fecha o mês de um colaborador
        ↓
status: "processing"  (sistema gera snapshots em background)
        ↓
status: "open"        (todos os snapshots prontos — colaboradores podem assinar)
        ↓
Colaborador revisa e assina  →  status do timesheet: "pending_manager"
        ↓ (ou)
Colaborador contesta         →  status do timesheet: "disputed"
        ↓
Admin/Gestor resolve          →  status do timesheet: "pending_manager"
        ↓
Admin/Gestor assina           →  status do timesheet: "completed"
        ↓
Todos os timesheets "completed"
        ↓
status do fechamento: "completed"
```

---

## Status dos Enums

### `ClosureStatus` (status do fechamento mensal)
| Valor | Descrição |
|-------|-----------|
| `processing` | Snapshots sendo gerados em background |
| `open` | Aberto para assinaturas dos colaboradores |
| `completed` | Todos os timesheets assinados |

### `TimesheetStatus` (status do timesheet individual)
| Valor | Descrição |
|-------|-----------|
| `pending_employee` | Aguardando revisão/assinatura do colaborador |
| `disputed` | Colaborador contestou |
| `pending_manager` | Colaborador assinou, aguarda gestor |
| `completed` | Assinado por ambos |

### `DisputeStatus`
| Valor | Descrição |
|-------|-----------|
| `open` | Contestação em aberto |
| `resolved` | Resolvida pelo admin/gestor |

---

## Endpoints — Admin / Gestor

### Fechar um mês

```
POST /api/v1/admin/monthly-closures
```

**Role requerida:** `admin`

**Body:**
```json
{
  "employee_id": "uuid",
  "reference_year": 2026,
  "reference_month": 4
}
```

**Resposta 201:**
```json
{
  "data": {
    "id": "uuid",
    "company_id": "uuid",
    "closed_by": {
      "id": "uuid",
      "name": "João Admin"
    },
    "employee_id": "uuid",
    "employee": {
      "id": "uuid",
      "name": "Maria Colaboradora"
    },
    "reference_year": 2026,
    "reference_month": 4,
    "status": "processing",
    "closed_at": "2026-05-21T10:00:00+00:00",
    "timesheets_count": 1,
    "created_at": "2026-05-21T10:00:00+00:00"
  }
}
```

**Erros:**
- `422` — mês atual ou futuro, ou mês já fechado:
```json
{
  "message": "...",
  "errors": {
    "reference_month": ["Este mês já foi fechado para este funcionário."]
  }
}
```

---

### Excluir um fechamento sem assinatura

```http
DELETE /api/v1/admin/monthly-closures/{closure}
Authorization: Bearer {token}
```

**Roles permitidas:** `admin`, `manager`, `area_manager`.

O fechamento deve pertencer à empresa do usuário autenticado. Gestores precisam ter permissão sobre o colaborador conforme `UserVisibilityService`, incluindo a regra existente para colaboradores sem área. Em fechamentos legados sem `employee_id`, precisam ter acesso a todos os colaboradores das folhas vinculadas; se não houver colaborador identificável, somente o administrador poderá excluir.

Não há body. Retorna **`204 No Content`** em caso de sucesso.

A exclusão é definitiva: remove o fechamento, suas folhas (snapshots) e contestações vinculadas, preservando as batidas de ponto e os registros de auditoria. Permite fechar novamente o mesmo período pelo endpoint de criação, que continua restrito a `admin`. Contestações abertas não impedem a exclusão quando não houver assinatura. Fechamentos em `processing` também podem ser excluídos; jobs pendentes para folhas removidas são descartados.

**Qualquer assinatura registrada bloqueia a operação**, seja de colaborador ou gestor, ativa ou invalidada (`superseded_at` preenchido). A condição é verificada em todas as folhas vinculadas, e não apenas pelo status do fechamento. Assinatura e exclusão simultâneas são serializadas: se a assinatura ocorrer primeiro, a exclusão é bloqueada; se a exclusão ocorrer primeiro, a assinatura retorna `404`.

**Erros:** `401` sem autenticação; `403` sem permissão de perfil, empresa ou área; `404` para fechamento inexistente (também possível para recursos ocultos pelo escopo de tenant); `422` quando já existe assinatura.

Exemplo de erro `422`:

```json
{
  "message": "Não é possível excluir um fechamento que já possui assinatura, mesmo que invalidada.",
  "errors": {
    "closure": [
      "Não é possível excluir um fechamento que já possui assinatura, mesmo que invalidada."
    ]
  }
}
```

A operação registra `monthly_closure.deleted` com autor, empresa e os dados anteriores do fechamento.

**Integração no front-end:**

- Usar o `id` do fechamento (`closure.id`), não o ID da folha nem o do colaborador.
- Exibir a ação para `admin`, `manager` e `area_manager`; o backend valida empresa, área e acesso da assinatura do plano. O recurso de fechamento não retorna `can_delete`: o status `open` ou `pending_employee` da folha não garante ausência de assinaturas no histórico.
- Antes de excluir, confirmar com: “Excluir este fechamento? As folhas e contestações vinculadas serão removidas. As batidas de ponto serão preservadas.”
- Desabilitar a ação enquanto a requisição estiver em andamento. Em `204`, não tentar interpretar JSON; fechar o detalhe removido, atualizar a lista de fechamentos e invalidar os dados de folhas/contestações desse fechamento.
- Em `422`, exibir `errors.closure[0]` (ou `message` como fallback) e manter o fechamento na tela. Em `404`, atualizar a lista. Em `403`, apresentar a mensagem do servidor. Em `401`, seguir o fluxo existente de autenticação. Em falha de rede ou `5xx`, apresentar erro e atualizar a lista antes de uma nova tentativa.
- A assinatura pode ocorrer depois que a tela foi carregada: tratar `422` mesmo se o botão estava habilitado.

Exemplo com Axios, considerando um cliente `api` já autenticado e com `baseURL` terminando em `/api/v1`:

```typescript
// Não repetir /api/v1 se o prefixo já estiver no baseURL.
export async function deleteMonthlyClosure(closureId: string): Promise<void> {
  await api.delete(`/admin/monthly-closures/${encodeURIComponent(closureId)}`);
  // Sucesso: 204, sem corpo. Atualizar as consultas da tela após o await.
}
```

---

### Listar fechamentos mensais

```
GET /api/v1/admin/monthly-closures
```

**Role requerida:** `admin`, `manager`, `area_manager`

**Query params:**
- `per_page` (int, default 20)

**Resposta 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "company_id": "uuid",
      "closed_by": { "id": "uuid", "name": "João Admin" },
      "employee_id": "uuid",
      "employee": { "id": "uuid", "name": "Maria Colaboradora" },
      "reference_year": 2026,
      "reference_month": 4,
      "status": "open",
      "closed_at": "2026-05-21T10:00:00+00:00",
      "timesheets_count": 1,
      "created_at": "2026-05-21T10:00:00+00:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

### Detalhe de um fechamento

```
GET /api/v1/admin/monthly-closures/{closure_id}
```

**Role requerida:** `admin`, `manager`, `area_manager`

**Resposta 200:** mesmo formato do objeto acima.

---

### Listar timesheets de um fechamento

```
GET /api/v1/admin/monthly-closures/{closure_id}/timesheets
```

**Role requerida:** `admin`, `manager`, `area_manager`

**Query params:**
- `per_page` (int, default 20)

**Resposta 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "closure_id": "uuid",
      "employee": { "id": "uuid", "name": "Maria Colaboradora" },
      "status": "pending_employee",
      "snapshot": {
        "employee_id": "uuid",
        "from": "2026-04-01",
        "to": "2026-04-30",
        "timezone": "America/Sao_Paulo",
        "totals": {
          "scheduled_minutes": 10080,
          "worked_minutes": 9840,
          "balance_minutes": -240,
          "overtime_minutes": 0,
          "absence_minutes": 240
        },
        "days": [
          {
            "date": "2026-04-01",
            "weekday": "tuesday",
            "is_holiday": false,
            "scheduled_minutes": 480,
            "worked_minutes": 475,
            "balance_minutes": -5,
            "entries": [
              { "type": "clock_in", "time": "09:00", "is_adjusted": false },
              { "type": "clock_out", "time": "18:00", "is_adjusted": false }
            ]
          }
        ]
      },
      "snapshot_generated_at": "2026-05-21T10:01:00+00:00",
      "signatures": [
        {
          "id": "uuid",
          "role": "employee",
          "signer": { "id": "uuid", "name": "Maria Colaboradora" },
          "signed_at": "2026-05-22T09:00:00+00:00",
          "ip_address": "192.168.1.1"
        }
      ],
      "open_dispute": null,
      "pdf_available": false,
      "created_at": "2026-05-21T10:00:00+00:00"
    }
  ],
  "links": { ... },
  "meta": { ... }
}
```

---

### Detalhe de um timesheet (admin)

```
GET /api/v1/admin/timesheets/{timesheet_id}
```

**Role requerida:** `admin`, `manager`, `area_manager` (visibilidade sobre o colaborador)

**Resposta 200:** mesmo formato do objeto timesheet acima.

---

### Assinar timesheet como gestor

```
POST /api/v1/admin/timesheets/{timesheet_id}/sign
```

**Role requerida:** `admin`, `manager`, `area_manager` (com visibilidade sobre o colaborador)

**Body:** vazio `{}`

**Resposta 200:**
```json
{
  "data": {
    "id": "uuid",
    "status": "completed",
    "signatures": [
      { "role": "employee", "signed_at": "...", "signer": { ... } },
      { "role": "manager",  "signed_at": "...", "signer": { ... } }
    ],
    ...
  }
}
```

**Erros:**
- `422` — timesheet não está em `pending_manager`:
```json
{
  "errors": { "status": ["..."] }
}
```
- `403` — gestor não tem visibilidade sobre o colaborador

---

### Resolver uma contestação

```
POST /api/v1/admin/timesheets/{timesheet_id}/disputes/{dispute_id}/resolve
```

**Role requerida:** `admin`, `manager`, `area_manager`

**Body:**
```json
{
  "resolution_note": "Verificado e corrigido no sistema."
}
```

**Resposta 200:**
```json
{
  "data": {
    "id": "uuid",
    "reason": "Batidas incorretas no período.",
    "status": "resolved",
    "resolution_note": "Verificado e corrigido no sistema.",
    "resolved_by": { "id": "uuid", "name": "João Admin" },
    "resolved_at": "2026-05-22T14:00:00+00:00",
    "created_at": "2026-05-21T16:00:00+00:00"
  }
}
```

> Ao resolver, o timesheet volta automaticamente para `pending_employee`.

---

## Endpoints — Colaborador

### Listar meus timesheets

```
GET /api/v1/employee/timesheets
```

**Role requerida:** `employee`

> Retorna apenas timesheets de fechamentos com status `open` ou `completed`.

**Query params:**
- `per_page` (int, default 20)

**Resposta 200:** lista paginada de timesheets (mesmo formato acima, sem campo `employee`).

---

### Detalhe do meu timesheet

```
GET /api/v1/employee/timesheets/{timesheet_id}
```

**Role requerida:** `employee` (somente o próprio)

**Resposta 200:** objeto timesheet completo.

---

### Assinar meu timesheet

```
POST /api/v1/employee/timesheets/{timesheet_id}/sign
```

**Role requerida:** `employee` (somente o próprio)

**Body:** vazio `{}`

**Resposta 200:**
```json
{
  "data": {
    "id": "uuid",
    "status": "pending_manager",
    "signatures": [
      {
        "id": "uuid",
        "role": "employee",
        "signer": { "id": "uuid", "name": "Maria Colaboradora" },
        "signed_at": "2026-05-22T09:00:00+00:00",
        "ip_address": "192.168.1.10"
      }
    ],
    ...
  }
}
```

**Erros:**
- `422` — existe contestação em aberto:
```json
{
  "errors": { "dispute": ["..."] }
}
```
- `422` — status não é `pending_employee`
- `403` — colaborador tentando assinar timesheet de outro

---

### Contestar meu timesheet

```
POST /api/v1/employee/timesheets/{timesheet_id}/dispute
```

**Role requerida:** `employee` (somente o próprio)

**Body:**
```json
{
  "reason": "Existem batidas incorretas no período."
}
```

**Resposta 201:**
```json
{
  "data": {
    "id": "uuid",
    "reason": "Existem batidas incorretas no período.",
    "status": "open",
    "resolution_note": null,
    "resolved_by": null,
    "resolved_at": null,
    "created_at": "2026-05-22T10:00:00+00:00"
  }
}
```

> Ao contestar, o timesheet muda automaticamente para `disputed`.

**Erros:**
- `422` — já existe uma contestação em aberto para este timesheet
- `422` — status não é `pending_employee`
- `403` — colaborador tentando contestar timesheet de outro

---

## Estrutura do `snapshot`

O snapshot é um JSON imutável gerado no momento do fechamento, com os dados de ponto do colaborador no período.

```json
{
  "employee_id": "uuid",
  "from": "2026-04-01",
  "to": "2026-04-30",
  "timezone": "America/Sao_Paulo",
  "totals": {
    "scheduled_minutes": 10080,
    "worked_minutes": 9840,
    "balance_minutes": -240,
    "overtime_minutes": 120,
    "absence_minutes": 360,
    "vacation_minutes": 0,
    "holiday_minutes": 0
  },
  "days": [
    {
      "date": "2026-04-01",
      "weekday": "tuesday",
      "is_holiday": false,
      "scheduled_minutes": 480,
      "worked_minutes": 475,
      "balance_minutes": -5,
      "entries": [
        {
          "type": "clock_in",
          "time": "09:02",
          "is_adjusted": false
        },
        {
          "type": "clock_out",
          "time": "18:00",
          "is_adjusted": false
        }
      ]
    }
  ]
}
```

> `snapshot` é `null` enquanto o fechamento está em `processing`. O campo `snapshot_generated_at` indica quando foi gerado.

---

## Regras de Negócio Importantes

1. **Só é possível fechar meses passados** — o mês atual e futuros retornam `422`.
2. **O fechamento é individual por colaborador** — `employee_id` é obrigatório e gera uma única `EmployeeTimesheet`.
3. **Não é possível fechar o mesmo mês duas vezes para o mesmo colaborador** — retorna `422`; outro colaborador da mesma empresa pode ser fechado no mesmo mês.
4. **O colaborador só assina quando não há contestação em aberto.**
5. **O gestor só assina após o colaborador** — o timesheet precisa estar em `pending_manager`.
6. **Ao resolver uma contestação**, o timesheet volta para `pending_employee` para o colaborador assinar novamente.
7. **O fechamento avança para `completed` automaticamente** quando o timesheet estiver em `completed`.
8. **O snapshot é gerado de forma assíncrona** (via job em background). O fechamento fica em `processing` até o snapshot estar pronto.
