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

### Intervalo (break) do turno

Quando o periodo abonado (`hours`) ou o turno inteiro (`full_day`) cruza o horario de intervalo configurado no shift (`break_start_time`/`break_end_time`), o sistema gera automaticamente os 4 eventos correspondentes (`work_start`, `break_start`, `break_end`, `work_end`) em vez de um unico bloco continuo. Os minutos de intervalo sao excluidos de `absence_minutes`/`worked_minutes`, exatamente como aconteceria num dia com ponto batido normalmente. Se o abono nao cruzar o intervalo do turno, apenas 2 eventos (`work_start`/`work_end`) sao gerados, como antes.

Exemplo: turno 08:00-17:00 com intervalo 12:00-13:00, abono `hours` das 11:00 as 14:00 -> gera `work_start@11:00`, `break_start@12:00`, `break_end@13:00`, `work_end@14:00`, e credita apenas 2h (120 min) de `absence_minutes`, nao as 3h (180 min) do periodo bruto.

### Aviso de cobertura incompleta

A resposta de `POST /admin/absences` inclui um campo `warnings` (array, pode vir vazio). Para abonos `hours`, se apos gerar os pontos o dia ainda ficar com debito de horas (a soma de trabalho real + abono nao fecha a jornada esperada), a resposta traz um aviso nao-bloqueante:

```json
{
  "id": "uuid-do-abono",
  "coverage_type": "hours",
  "...": "...",
  "warnings": [
    {
      "date": "2026-06-10",
      "missing_minutes": 34,
      "missing_hhmm": "00:34",
      "message": "Abono nao cobre a jornada esperada do dia 2026-06-10. Faltam 00:34 para completar a carga horaria."
    }
  ]
}
```

Quando o abono fecha corretamente a jornada esperada (ou e do tipo `full_day`, que sempre preenche a jornada inteira), `warnings` vem como `[]`. O aviso e apenas informativo: o abono e criado normalmente mesmo com debito remanescente, cabendo a quem lancou decidir se cadastra um abono complementar ou ajusta o horario.

`DELETE /admin/absences/{absence}`

Remove um abono/ausencia lancado incorretamente e tambem remove os `time_entries` gerados para ele (`source=absence_allowance`). A exclusao e bloqueada quando qualquer mes coberto pela absence ja possui fechamento mensal para o funcionario alvo. Fechamento de outro funcionario no mesmo mes nao bloqueia. Pontos manuais do usuario nao sao removidos.

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

- Mes fechado do funcionario alvo bloqueia criacao.
- Ferias no periodo bloqueiam criacao.
- Ausencia, atestado ou abono pendente/efetivo no periodo bloqueia criacao.
- `pending`, `rejected` e `canceled` nao abonam no calculo.
- `full_day` gera pontos de entrada/saida conforme o shift do dia.
- `hours` gera pontos conforme o intervalo informado, limitado ao dia/shift valido.
- Se o periodo gerado cruzar o intervalo (`break_start_time`/`break_end_time`) do shift, sao gerados 4 pontos (`work_start`/`break_start`/`break_end`/`work_end`) e o intervalo e excluido de `absence_minutes`/`worked_minutes`. Ver secao "Intervalo (break) do turno" acima.
- A resposta traz `warnings` (nao-bloqueante) quando um abono `hours` nao fecha a jornada esperada do dia. Ver secao "Aviso de cobertura incompleta" acima.
- Exclusao de absence remove apenas pontos gerados pelo sistema para aquela `absence_id`.
