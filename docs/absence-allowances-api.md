# Abonos Gerais

Base API: `/api/v1`

## Admin / Manager / Area Manager

`POST /admin/absences`

Cria um abono geral para qualquer motivo. Por padrao, cria com:

- `type = excused_absence`
- `status = recorded`
- `counts_for_accrual = true`

Registros com `status = recorded` ou `approved` entram no calculo de ponto e reduzem os minutos esperados. Assim, um dia inteiro abonado nao gera debito de hora extra/banco de horas.

## Dia Inteiro

```json
{
  "user_id": "uuid",
  "coverage_type": "full_day",
  "start_date": "2026-06-01",
  "end_date": "2026-06-03",
  "comment": "Abono administrativo"
}
```

## Horas

```json
{
  "user_id": "uuid",
  "type": "personal_reason",
  "coverage_type": "hours",
  "date": "2026-06-01",
  "start_time": "10:00",
  "end_time": "12:00",
  "comment": "Abono por motivo pessoal"
}
```

## Campos

| Campo | Obrigatorio | Observacao |
| --- | --- | --- |
| `user_id` | Sim | Funcionario alvo |
| `type` | Nao | Motivo/categoria tecnica. Default: `excused_absence` |
| `coverage_type` | Sim | `full_day` ou `hours` |
| `start_date` | Sim para `full_day` | Data inicial |
| `end_date` | Nao | Data final |
| `date` | Sim para `hours` | Data do abono parcial |
| `start_time` | Sim para `hours` | `HH:MM` |
| `end_time` | Sim para `hours` | `HH:MM`, maior que `start_time` |
| `status` | Nao | Default: `recorded`. Efetivos: `recorded`, `approved` |
| `comment` | Nao | Texto livre |
| `counts_for_accrual` | Nao | Default: `true` |

## Regras

- Mes fechado bloqueia criacao.
- Ferias no periodo bloqueiam criacao.
- Ausencia, atestado ou abono pendente/efetivo no periodo bloqueia criacao.
- `pending`, `rejected` e `canceled` nao abonam no calculo.
