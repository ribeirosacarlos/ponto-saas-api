# Notas Tecnicas - Ponto, Horas Extras e Abonos (2026-06-08)

Documento criado a partir dos commits da branch `main` feitos em 2026-06-08 e do `CHANGELOG.md` nas versoes `1.8.1` a `1.8.6`.

## Resumo Executivo

As mudancas do dia estabilizam o calculo de ponto e banco de horas em tres frentes:

- calculo canonico por dia em `summary`, com separacao entre horas reais trabalhadas e horas oficiais consideradas no saldo;
- abonos/ausencias efetivos entrando no calculo sem gerar debito indevido;
- visibilidade do gestor de area incluindo dias de ausencia efetiva, mesmo sem batidas reais.

## Releases Analisados

| Versao | Tema principal | Impacto documentado |
| --- | --- | --- |
| `1.8.1` | Pausas e saldo | `worked_minutes` permanece como tempo real dos pares `in`/`out`; pausas excedidas aparecem em campos proprios e impactam o saldo por diferenca contra a jornada esperada. |
| `1.8.2` | Dia corrente e totais | O dia atual nao fecha saldo de overtime. Ele pode aparecer em `days`, mas fica com `is_finalized=false` e nao entra em `totals`. |
| `1.8.3` | Abonos gerais | `POST /v1/admin/absences` passa a usar `AbsenceAllowanceService`; tipo padrao `excused_absence`; validacoes de cobertura e status foram ampliadas. |
| `1.8.4` | Ausencias no calculo | Status efetivos (`recorded`, `approved`) abonam o ponto; status nao efetivos nao alteram saldo. Documentacao de `time-entry` foi ajustada para minutos trabalhados oficiais. |
| `1.8.5` | Ausencias virtuais | Dias de ausencia de dia inteiro podem gerar entradas virtuais para consultas de gestor de area. |
| `1.8.6` | Consolidacao no gestor de area | `TimeEntryController` do gestor de area foi consolidado para incluir ausencia e abono no retorno plano e agrupado. |

## Contrato De Calculo

### Campos canonicos

Nos endpoints agregados por dia, `summary` e a fonte canonica. Os aliases no nivel raiz existem por retrocompatibilidade.

Campos principais:

- `raw_worked_minutes` / `actual_worked_minutes`: soma real dos pares fechados `in`/`out`.
- `worked_minutes`: total oficial considerado no saldo; pode incluir credito de abono efetivo.
- `expected_minutes`: jornada esperada do dia, salvo feriado, folga regular ou ferias.
- `real_break_minutes` / `actual_break_minutes`: soma real das pausas entre pares.
- `allowed_break_minutes`: limite diario configurado no shift.
- `counted_break_minutes`: pausa considerada ate o limite permitido.
- `exceeded_break_minutes`: pausa acima do limite permitido.
- `balance_minutes`: `worked_minutes - expected_minutes` apenas para dias finalizados.
- `is_finalized`: `false` para o dia atual no fuso da empresa.

### Dia corrente

O dia corrente e exibido para acompanhamento operacional, mas nao fecha banco de horas:

- `is_finalized=false`;
- `balance_minutes=0`;
- `extra_minutes=0`;
- `debt_minutes=0`;
- nao entra em `totals.worked_minutes`, `totals.expected_minutes`, `totals.balance_minutes`, `totals.extra_minutes` ou `totals.debt_minutes`.

### Pausas

O sistema nao soma pausa permitida como tempo trabalhado. O tempo trabalhado real vem dos pares `in`/`out`.

Exemplo:

- Jornada esperada: 340 minutos.
- Pausa permitida: 20 minutos.
- Pausa real: 25 minutos.
- Pausa excedida: 5 minutos.
- Tempo trabalhado real/oficial: soma dos pares, sem acrescimo automatico da pausa permitida.
- Saldo: `worked_minutes - expected_minutes`.

### Pares incompletos

Pares fechados continuam contando mesmo quando existe uma entrada aberta no mesmo dia.

- `has_incomplete_entries=true` indica sequencia incompleta.
- `open_session=true` indica entrada `in` sem `out`.
- `ignored=true` no overtime so ocorre quando a sequencia e incompleta e `worked_minutes=0`.

## Abonos E Ausencias

### Endpoint administrativo

`POST /v1/admin/absences`

Cria abono geral para funcionario visivel ao ator autenticado. O ator pode ser `admin`, `manager` ou `area_manager`, respeitando a visibilidade aplicada pelo backend.

Padroes:

- `type=excused_absence` quando `type` nao for informado;
- `status=recorded` quando `status` nao for informado;
- `counts_for_accrual=true` quando `counts_for_accrual` nao for informado.

### Status efetivos

Apenas estes status abonam o calculo:

- `recorded`;
- `approved`.

Status sem efeito no ponto:

- `pending`;
- `rejected`;
- `canceled`.

### Cobertura de dia inteiro

Ausencia efetiva de dia inteiro:

- preserva `expected_minutes` da jornada, exceto em feriado, folga regular ou ferias;
- credita `worked_minutes` oficial ate a jornada esperada;
- nao remove batidas reais ja existentes;
- pode gerar entradas persistidas (`in` e `out`) com `source=absence_allowance`, `device_type=system` e `absence_id` para exibicao em consultas do gestor de area.

### Cobertura por horas

Ausencia efetiva por horas:

- calcula `absence_minutes` pelo intervalo informado;
- em jornada fixa, considera apenas a sobreposicao com a jornada e remove sobreposicao com a pausa configurada;
- em jornada flexivel ou sem grade detalhada, usa a duracao informada limitada por `expected_minutes`;
- credita `worked_minutes` oficial com `raw_worked_minutes + absence_minutes`, limitado a `expected_minutes`.

### Bloqueios

A criacao de abono bloqueia quando houver:

- fechamento mensal no periodo;
- ferias no periodo;
- ausencia, atestado ou abono sobreposto com status `pending`, `approved` ou `recorded`.

## Gestor De Area

### `GET /v1/area-manager/team/entries` sem `user_id`

Retorna batidas brutas paginadas, respeitando visibilidade do gestor e excluindo ajustes rejeitados.

Cada batida pode trazer:

- `work_date`;
- `day_summary`.

Dias com absence efetiva e shift valido podem aparecer como entradas persistidas no mesmo formato de batida:

- `absence=true`;
- `source=absence_allowance`;
- `device_type=system`;
- `absence_id`;
- `absence_type`;
- `absence_coverage_type`;
- `day_summary`.

### `GET /v1/area-manager/team/entries` com `user_id`

Retorna dias agrupados para o funcionario solicitado e ignora paginacao.

Formato:

- `data[].date`;
- `data[].employee_id`;
- `data[].user`;
- `data[].day_summary`;
- `data[].entries[]`;
- `total_days`;
- `total_entries`.

Dias apenas com ausencia efetiva de dia inteiro podem entrar no agrupamento mesmo sem batidas reais, usando as entradas virtuais geradas pelo calculo de overtime.

## Relacao Com O CHANGELOG

O `CHANGELOG.md` registra releases pequenos e sequenciais no mesmo dia. Algumas mensagens sao repetidas por merges e releases automatizados do `release-please`; por isso, a leitura tecnica deve considerar o estado final da `main`, nao apenas os titulos individuais.

Arquivos de referencia direta:

- `docs/time-entry-api.md`;
- `docs/absence-allowances-api.md`;
- `app/Services/TimeEntry/TimesheetCalculationService.php`;
- `app/Http/Controllers/Api/AreaManager/TimeEntryController.php`;
- `app/Services/AbsenceAllowanceService.php`;
- `app/Models/Absence.php`;
- `CHANGELOG.md`.
