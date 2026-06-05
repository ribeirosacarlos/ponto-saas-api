# Atestados Médicos - Guia Rápido Frontend

Base API: `/api/v1`

## Estados

| Status | Significado | Efeito no ponto |
| --- | --- | --- |
| `pending` | Enviado pelo funcionário, aguardando revisão | Não abona |
| `approved` | Aprovado pelo gestor/admin | Abona |
| `rejected` | Rejeitado pelo gestor/admin | Não abona |
| `canceled` | Cancelado pelo funcionário | Não abona |

## Tipos De Abono

Dia inteiro:

```json
{
  "coverage_type": "full_day",
  "start_date": "2026-06-01",
  "end_date": "2026-06-03",
  "comment": "Atestado médico"
}
```

Horas:

```json
{
  "coverage_type": "hours",
  "date": "2026-06-01",
  "start_time": "10:00",
  "end_time": "12:00",
  "comment": "Consulta médica"
}
```

## Funcionário

`GET /employee/medical-certificates`

Query opcional: `status`, `from`, `to`, `per_page`.

`POST /employee/medical-certificates`

Cria solicitação com `status = pending`. Use `multipart/form-data` se enviar `files[]`.

`GET /employee/medical-certificates/{id}`

`DELETE /employee/medical-certificates/{id}`

Cancela apenas solicitação própria com `status = pending`.

## Admin / Manager / Area Manager

`GET /admin/medical-certificates`

Query opcional: `user_id`, `status`, `from`, `to`, `per_page`.

`POST /admin/medical-certificates`

Mesmo payload do funcionário, adicionando `user_id`. Cria direto com `status = approved`.

`PATCH /admin/medical-certificates/{id}/approve`

Sem body.

`PATCH /admin/medical-certificates/{id}/reject`

```json
{
  "rejection_reason": "Documento ilegível"
}
```

## Campos De Criação

| Campo | Obrigatório | Observação |
| --- | --- | --- |
| `user_id` | Sim no admin | Funcionário alvo |
| `coverage_type` | Sim | `full_day` ou `hours` |
| `start_date` | Sim para `full_day` | Data inicial |
| `end_date` | Não | Data final |
| `date` | Sim para `hours` | Data do atestado parcial |
| `start_time` | Sim para `hours` | `HH:MM` |
| `end_time` | Sim para `hours` | `HH:MM`, maior que `start_time` |
| `comment` | Não | Texto livre |
| `files[]` | Não | Um ou mais anexos |

## Resposta

```json
{
  "data": {
    "id": "uuid",
    "user_id": "uuid",
    "type": "sick_leave",
    "coverage_type": "full_day",
    "start_date": "2026-06-01",
    "end_date": "2026-06-03",
    "start_time": null,
    "end_time": null,
    "status": "pending",
    "comment": "Atestado médico",
    "counts_for_accrual": true,
    "approved_by": null,
    "approved_at": null,
    "rejected_by": null,
    "rejected_at": null,
    "rejection_reason": null,
    "canceled_by": null,
    "canceled_at": null,
    "documents": [],
    "created_at": "2026-06-05T10:00:00+00:00",
    "updated_at": "2026-06-05T10:00:00+00:00"
  }
}
```

Listagens usam paginação padrão do Laravel Resource Collection.

## Regras Para UI

- Anexo é opcional.
- Funcionário sempre cria como `pending`.
- Admin/manager/area_manager cria direto como `approved`.
- Mostrar cancelar apenas para funcionário dono e `status = pending`.
- Mostrar aprovar/rejeitar apenas para gestor/admin e `status = pending`.
- Bloqueios retornam `422`, normalmente em `start_date`.
- Mês fechado bloqueia criação, aprovação e cancelamento.
- Férias ou ausência sobreposta bloqueiam criação/aprovação.
- Atestado aprovado não remove batidas já feitas no dia.
