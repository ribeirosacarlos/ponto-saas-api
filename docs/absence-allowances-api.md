# Abonos Gerais

Base API: `/api/v1`

## Admin / Manager / Area Manager

`POST /admin/absences`

Cria um abono geral para qualquer motivo. Por padrao, cria com:

- `type = excused_absence`
- `status = recorded`
- `counts_for_accrual = true`

Registros com `status = recorded` ou `approved` entram no calculo de ponto como trabalho abonado. A jornada esperada do shift continua sendo exibida, e o tempo abonado entra em `worked_minutes` oficial para nao gerar debito indevido de hora extra/banco de horas.

Quando ha shift valido, abonos efetivos tambem geram `time_entries` persistidos com `source=absence_allowance`, `device_type=system` e `absence_id`. Esses pontos aparecem nas listagens de ponto, mas nao entram em `raw_worked_minutes`; o calculo oficial usa `absence_minutes`/`worked_minutes` para evitar duplicidade.

`DELETE /admin/absences/{absence}`

Remove um abono/ausencia lancado incorretamente e tambem remove os `time_entries` gerados para ele (`source=absence_allowance`). A exclusao e bloqueada quando qualquer mes coberto pela absence ja possui fechamento mensal. Pontos manuais do usuario nao sao removidos.

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
- `full_day` gera pontos de entrada/saida conforme o shift do dia.
- `hours` gera pontos conforme o intervalo informado, limitado ao dia/shift valido.
- Exclusao de absence remove apenas pontos gerados pelo sistema para aquela `absence_id`.
