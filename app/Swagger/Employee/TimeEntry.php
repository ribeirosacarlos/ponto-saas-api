<?php

namespace App\Swagger\Employee;

/**
 * @OA\Tag(
 *     name="Employee - Time Entries",
 *     description="Rotas de registro e consulta de batidas de ponto do funcionario"
 * )
 */
class TimeEntry {}

/**
 * ==========================================
 * Registrar batida (clock)
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/employee/clock",
 *     summary="Registrar batida de ponto",
 *     description="O backend define automaticamente o type (in/out) com base no proximo evento esperado da jornada. Quando a empresa possui geolocalizacao habilitada no plano, latitude e longitude passam a ser obrigatorias.",
 *     tags={"Employee - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=false,
 *         description="Dados da batida. Latitude e longitude sao obrigatorias quando a geolocalizacao estiver habilitada para a empresa.",
 *         @OA\JsonContent(
 *             @OA\Property(property="latitude", type="number", format="double", nullable=true, example=-23.550520),
 *             @OA\Property(property="longitude", type="number", format="double", nullable=true, example=-46.633308),
 *             @OA\Property(property="source", type="string", nullable=true, example="web")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Batida normal registrada com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="entry", ref="#/components/schemas/EmployeeTimeEntry"),
 *             @OA\Property(property="next_event", ref="#/components/schemas/EmployeeNextExpectedEvent", nullable=true)
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=202,
 *         description="Batida fora da jornada convertida em solicitacao de ajuste",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Fora da jornada prevista. Solicitacao de ajuste criada."),
 *             @OA\Property(property="status", type="string", example="adjustment_requested"),
 *             @OA\Property(property="adjustment", ref="#/components/schemas/EmployeeTimeEntry")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Regra de negocio impedindo a batida (ex.: ferias ou intervalo minimo de 1 minuto)"
 *     )
 * )
 */
class TimeEntryClock {}

/**
 * ==========================================
 * Listar batidas do proprio usuario
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/employee/entries",
 *     summary="Lista as batidas do funcionario autenticado",
 *     tags={"Employee - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada de batidas",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=20),
 *             @OA\Property(property="total", type="integer", example=42),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/EmployeeTimeEntry")
 *             )
 *         )
 *     )
 * )
 */
class TimeEntryMyEntries {}

/**
 * ==========================================
 * Historico diario de marcacoes
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/employee/entries/history",
 *     summary="Historico diario de marcacoes do funcionario autenticado",
 *     tags={"Employee - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="from",
 *         in="query",
 *         required=false,
 *         description="Data inicial (YYYY-MM-DD).",
 *         @OA\Schema(type="string", format="date", example="2026-02-01")
 *     ),
 *     @OA\Parameter(
 *         name="to",
 *         in="query",
 *         required=false,
 *         description="Data final (YYYY-MM-DD).",
 *         @OA\Schema(type="string", format="date", example="2026-02-28")
 *     ),
 *     @OA\Parameter(
 *         name="limit",
 *         in="query",
 *         required=false,
 *         description="Quantidade de dias para retorno quando from/to nao forem informados.",
 *         @OA\Schema(type="integer", example=7)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Resumo diario retornado com sucesso",
 *         @OA\JsonContent(ref="#/components/schemas/EmployeeTimeEntryHistoryResponse")
 *     )
 * )
 */
class EmployeeTimeEntryHistory {}

/**
 * ==========================================
 * Status de ponto aberto
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/employee/time-entries/open-status",
 *     summary="Consulta status de ponto em aberto usando jornada atribuida",
 *     description="open=true quando ultimo evento concluido e um IN e o proximo OUT esperado esta atrasado em >= 30 minutos.",
 *     tags={"Employee - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Status atual de ponto aberto",
 *         @OA\JsonContent(
 *             @OA\Property(property="open", type="boolean", example=true),
 *             @OA\Property(property="open_reason", type="string", nullable=true, example="Expected out event overdue by at least 30 minutes."),
 *             @OA\Property(property="expected_next_out_at", type="string", format="date-time", nullable=true, example="2026-02-17T06:00:00+01:00"),
 *             @OA\Property(property="last_in_at", type="string", format="date-time", nullable=true, example="2026-02-16T22:01:00+01:00"),
 *             @OA\Property(
 *                 property="shift_day",
 *                 type="object",
 *                 nullable=true,
 *                 @OA\Property(property="weekday", type="integer", example=1),
 *                 @OA\Property(property="is_working_day", type="boolean", example=true)
 *             ),
 *             @OA\Property(property="assignment_id", type="string", format="uuid", nullable=true),
 *             @OA\Property(property="next_event", ref="#/components/schemas/EmployeeNextExpectedEvent", nullable=true),
 *             @OA\Property(property="is_outside_shift", type="boolean", example=false)
 *         )
 *     )
 * )
 */
class TimeEntryOpenStatus {}

/**
 * ==========================================
 * Jornada atual do funcionario
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/employee/shift",
 *     summary="Retorna a jornada atribuida ao funcionario",
 *     description="Inclui dias da jornada e eventos normalizados por dia (work_start, break_start, break_end, work_end).",
 *     tags={"Employee - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Jornada atual e atribuicao",
 *         @OA\JsonContent(
 *             @OA\Property(property="shift", ref="#/components/schemas/EmployeeShift", nullable=true),
 *             @OA\Property(property="assignment", ref="#/components/schemas/EmployeeShiftAssignment", nullable=true)
 *         )
 *     )
 * )
 */
class EmployeeShiftShow {}

/**
 * @OA\Schema(
 *     schema="EmployeeTimeEntry",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="company_id", type="string", format="uuid"),
 *     @OA\Property(property="user_id", type="string", format="uuid"),
 *     @OA\Property(property="user_shift_id", type="string", format="uuid", nullable=true),
 *     @OA\Property(property="clocked_at", type="string", format="date-time"),
 *     @OA\Property(property="type", type="string", enum={"in", "out"}, example="in"),
 *     @OA\Property(property="event_kind", type="string", nullable=true, example="work_start"),
 *     @OA\Property(property="latitude", type="string", nullable=true, example="-23.550520"),
 *     @OA\Property(property="longitude", type="string", nullable=true, example="-46.633308"),
 *     @OA\Property(property="source", type="string", example="web"),
 *     @OA\Property(property="adjustment_status", type="string", nullable=true, example="pending"),
 *     @OA\Property(property="adjustment_reason", type="string", nullable=true),
 *     @OA\Property(property="adjustment_requested_at", type="string", format="date-time", nullable=true)
 * )
 */
class EmployeeTimeEntrySchema {}

/**
 * @OA\Schema(
 *     schema="EmployeeNextExpectedEvent",
 *     @OA\Property(property="kind", type="string", example="break_start"),
 *     @OA\Property(property="expected_type", type="string", enum={"in", "out"}, example="out"),
 *     @OA\Property(property="expected_at", type="string", format="date-time", example="2026-02-16T12:00:00+01:00"),
 *     @OA\Property(property="day_offset", type="integer", example=0)
 * )
 */
class EmployeeNextExpectedEventSchema {}

/**
 * @OA\Schema(
 *     schema="EmployeeShift",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string", example="Turno Manha"),
 *     @OA\Property(property="start_time", type="string", nullable=true, example="08:00"),
 *     @OA\Property(property="end_time", type="string", nullable=true, example="17:00"),
 *     @OA\Property(property="is_flexible", type="boolean", example=false),
 *     @OA\Property(property="is_default", type="boolean", example=true),
 *     @OA\Property(
 *         property="shift_days",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EmployeeShiftDay")
 *     )
 * )
 */
class EmployeeShiftSchema {}

/**
 * @OA\Schema(
 *     schema="EmployeeShiftDay",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="weekday", type="integer", example=1),
 *     @OA\Property(property="is_working_day", type="boolean", example=true),
 *     @OA\Property(property="start_time", type="string", nullable=true, example="08:00"),
 *     @OA\Property(property="end_time", type="string", nullable=true, example="17:00"),
 *     @OA\Property(property="break_start_time", type="string", nullable=true, example="12:00"),
 *     @OA\Property(property="break_end_time", type="string", nullable=true, example="13:00"),
 *     @OA\Property(property="break_minutes", type="integer", nullable=true, example=60),
 *     @OA\Property(
 *         property="events",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EmployeeShiftDayEvent")
 *     )
 * )
 */
class EmployeeShiftDaySchema {}

/**
 * @OA\Schema(
 *     schema="EmployeeShiftDayEvent",
 *     @OA\Property(property="kind", type="string", example="work_start"),
 *     @OA\Property(property="expected_time", type="string", example="08:00:00"),
 *     @OA\Property(property="day_offset", type="integer", example=0),
 *     @OA\Property(property="expected_type", type="string", enum={"in", "out"}, example="in"),
 *     @OA\Property(property="sort_order", type="integer", example=10)
 * )
 */
class EmployeeShiftDayEventSchema {}

/**
 * @OA\Schema(
 *     schema="EmployeeShiftAssignment",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="start_date", type="string", format="date", example="2025-12-01"),
 *     @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2025-12-31")
 * )
 */
class EmployeeShiftAssignmentSchema {}

/**
 * @OA\Schema(
 *     schema="TimeEntryDaySummary",
 *     @OA\Property(property="worked_minutes", type="integer", example=490),
 *     @OA\Property(property="worked_hhmm", type="string", example="08:10"),
 *     @OA\Property(property="expected_minutes", type="integer", example=480),
 *     @OA\Property(property="expected_hhmm", type="string", example="08:00"),
 *     @OA\Property(property="balance_minutes", type="integer", example=10),
 *     @OA\Property(property="balance_hhmm", type="string", example="+00:10"),
 *     @OA\Property(property="extra_minutes", type="integer", example=10),
 *     @OA\Property(property="extra_hhmm", type="string", example="00:10"),
 *     @OA\Property(property="debt_minutes", type="integer", example=0),
 *     @OA\Property(property="debt_hhmm", type="string", example="00:00"),
 *     @OA\Property(property="status", type="string", enum={"extra","debt","even"}, example="extra"),
 *     @OA\Property(property="allowed_break_minutes", type="integer", example=60),
 *     @OA\Property(property="exceeded_break_minutes", type="integer", example=0),
 *     @OA\Property(property="has_incomplete_entries", type="boolean", example=false),
 *     @OA\Property(property="open_session", type="boolean", example=false)
 * )
 */
class TimeEntryDaySummarySchema {}

/**
 * @OA\Schema(
 *     schema="EmployeeTimeEntryHistoryDay",
 *     @OA\Property(property="date", type="string", format="date", example="2026-02-08"),
 *     @OA\Property(property="first_in", type="string", format="date-time", nullable=true, example="2026-02-08T08:05:00-03:00"),
 *     @OA\Property(property="last_out", type="string", format="date-time", nullable=true, example="2026-02-08T17:45:00-03:00"),
 *     @OA\Property(property="worked_minutes", type="integer", example=490),
 *     @OA\Property(property="worked_hhmm", type="string", example="08:10"),
 *     @OA\Property(property="expected_minutes", type="integer", example=480),
 *     @OA\Property(property="expected_hhmm", type="string", example="08:00"),
 *     @OA\Property(property="balance_minutes", type="integer", example=10),
 *     @OA\Property(property="balance_hhmm", type="string", example="+00:10"),
 *     @OA\Property(property="status", type="string", enum={"extra","debt","even"}, example="extra"),
 *     @OA\Property(property="summary", ref="#/components/schemas/TimeEntryDaySummary"),
 *     @OA\Property(property="open_day", type="boolean", example=false)
 * )
 */
class EmployeeTimeEntryHistoryDaySchema {}

/**
 * @OA\Schema(
 *     schema="EmployeeTimeEntryHistoryResponse",
 *     @OA\Property(property="from", type="string", format="date", example="2026-02-01"),
 *     @OA\Property(property="to", type="string", format="date", example="2026-02-28"),
 *     @OA\Property(property="timezone", type="string", example="America/Sao_Paulo"),
 *     @OA\Property(
 *         property="days",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EmployeeTimeEntryHistoryDay")
 *     )
 * )
 */
class EmployeeTimeEntryHistoryResponseSchema {}

/**
 * ==========================================
 * Horas trabalhadas hoje
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/employee/worked-today",
 *     summary="Resumo das horas trabalhadas hoje pelo funcionario autenticado",
 *     description="Calcula pares IN/OUT do dia e apura horas descontando intervalos esperados/registrados.",
 *     tags={"Employee - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Resumo diario das horas trabalhadas",
 *         @OA\JsonContent(
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 ref="#/components/schemas/EmployeeWorkedToday"
 *             )
 *         )
 *     )
 * )
 */
class EmployeeWorkedToday {}

/**
 * @OA\Schema(
 *     schema="EmployeeWorkedToday",
 *     @OA\Property(property="date", type="string", format="date", example="2025-12-19"),
 *     @OA\Property(property="worked_seconds", type="integer", example=28800),
 *     @OA\Property(property="worked_minutes", type="integer", example=480),
 *     @OA\Property(property="worked_hhmm", type="string", example="08:00"),
 *     @OA\Property(property="worked_hours_decimal", type="number", format="float", example=8.0),
 *     @OA\Property(property="expected_break_minutes", type="integer", example=60),
 *     @OA\Property(property="break_seconds_deducted", type="integer", example=3600),
 *     @OA\Property(property="open_session", type="boolean", example=false),
 *     @OA\Property(property="expected_minutes", type="integer", example=480),
 *     @OA\Property(property="expected_hhmm", type="string", example="08:00"),
 *     @OA\Property(property="balance_minutes", type="integer", example=0),
 *     @OA\Property(property="balance_hhmm", type="string", example="00:00"),
 *     @OA\Property(property="status", type="string", enum={"extra","debt","even"}, example="even"),
 *     @OA\Property(property="summary", ref="#/components/schemas/TimeEntryDaySummary"),
 *     @OA\Property(
 *         property="details",
 *         type="object",
 *         @OA\Property(
 *             property="pairs",
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/EmployeeWorkedTodayPair")
 *         ),
 *         @OA\Property(
 *             property="entries",
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/EmployeeWorkedTodayEntry")
 *         )
 *     )
 * )
 */
class EmployeeWorkedTodaySchema {}

/**
 * @OA\Schema(
 *     schema="EmployeeWorkedTodayPair",
 *     @OA\Property(property="in", type="string", format="date-time", example="2025-12-19T09:00:00-03:00"),
 *     @OA\Property(property="out", type="string", format="date-time", example="2025-12-19T12:30:00-03:00"),
 *     @OA\Property(property="seconds", type="integer", example=12600)
 * )
 */
class EmployeeWorkedTodayPairSchema {}

/**
 * @OA\Schema(
 *     schema="EmployeeWorkedTodayEntry",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="clocked_at", type="string", format="date-time", example="2025-12-19T08:00:00+00:00"),
 *     @OA\Property(property="type", type="string", nullable=true, example="in"),
 *     @OA\Property(property="event_kind", type="string", nullable=true, example="work_start"),
 *     @OA\Property(property="adjustment_status", type="string", nullable=true, example="pending"),
 *     @OA\Property(property="adjustment_reason", type="string", nullable=true, example="Fora do turno/jornada."),
 *     @OA\Property(property="source", type="string", nullable=true, example="web")
 * )
 */
class EmployeeWorkedTodayEntrySchema {}
