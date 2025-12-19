<?php

namespace App\Swagger\Employee;

/**
 * @OA\Tag(
 *     name="Employee - Time Entries",
 *     description="Rotas de registro e consulta de batidas de ponto do funcionário"
 * )
 */
class TimeEntry {}


/**
 * ==========================================
 * Registrar batida (Clock IN/OUT)
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/employee/clock",
 *     summary="Registrar batida de ponto (entrada ou saída)",
 *     description="O funcionário registra uma batida de ponto. A localização é opcional.",
 *     tags={"Employee - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         description="Dados da batida",
 *         @OA\JsonContent(
 *             required={"type"},
 *             @OA\Property(property="type", type="string", enum={"in", "out"}, example="in"),
 *             @OA\Property(property="latitude", type="string", nullable=true, example="-23.550520"),
 *             @OA\Property(property="longitude", type="string", nullable=true, example="-46.633308")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Batida registrada com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="user_id", type="integer", example=10),
 *             @OA\Property(property="clocked_at", type="string", example="2025-02-10T14:32:20Z"),
 *             @OA\Property(property="type", type="string", example="in"),
 *             @OA\Property(property="latitude", type="string", nullable=true, example="-23.550520"),
 *             @OA\Property(property="longitude", type="string", nullable=true, example="-46.633308"),
 *             @OA\Property(property="source", type="string", example="web")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=403,
 *         description="Usuário não autorizado a registrar batida"
 *     )
 * )
 */
class TimeEntryClock {}



/**
 * ==========================================
 * Listar batidas do próprio usuário
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/employee/entries",
 *     summary="Lista as batidas do próprio funcionário autenticado",
 *     description="Retorna uma listagem paginada dos registros de batida do usuário.",
 *     tags={"Employee - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         required=false,
 *         description="Número da página",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista de batidas",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=20),
 *             @OA\Property(property="total", type="integer", example=42),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="user_id", type="integer", example=10),
 *                     @OA\Property(property="clocked_at", type="string", example="2025-02-10T14:32:20Z"),
 *                     @OA\Property(property="type", type="string", example="out"),
 *                     @OA\Property(property="latitude", type="string", nullable=true, example="-23.550520"),
 *                     @OA\Property(property="longitude", type="string", nullable=true, example="-46.633308"),
 *                     @OA\Property(property="source", type="string", example="web")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class TimeEntryMyEntries {}


/**
 * ==========================================
 * Status de ponto aberto
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/employee/time-entries/open-status",
 *     summary="Consulta se existe uma batida pendente para hoje",
 *     description="Retorna o status do dia atual do funcionário, incluindo próximo passo esperado e dados da jornada.",
 *     tags={"Employee - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Status da jornada do dia atual",
 *         @OA\JsonContent(
 *             @OA\Property(property="date", type="string", format="date", example="2025-12-15"),
 *             @OA\Property(property="has_open_entry", type="boolean", example=true),
 *             @OA\Property(property="open_type", type="string", nullable=true, example="work"),
 *             @OA\Property(
 *                 property="last_entry",
 *                 type="object",
 *                 nullable=true,
 *                 @OA\Property(property="id", type="string", format="uuid"),
 *                 @OA\Property(property="type", type="string", example="in"),
 *                 @OA\Property(property="clocked_at", type="string", format="date-time", example="2025-12-15T08:12:00-03:00")
 *             ),
 *             @OA\Property(property="next_action", type="string", example="clock_out"),
 *             @OA\Property(
 *                 property="shift",
 *                 type="object",
 *                 nullable=true,
 *                 @OA\Property(property="start", type="string", example="08:00"),
 *                 @OA\Property(property="end", type="string", example="17:00"),
 *                 @OA\Property(property="is_within_shift_window", type="boolean", nullable=true),
 *                 @OA\Property(property="late", type="boolean", nullable=true),
 *                 @OA\Property(property="tolerance_minutes", type="integer", example=10)
 *             )
 *         )
 *     )
 * )
 */
class TimeEntryOpenStatus {}

/**
 * ==========================================
 * Jornada atual do funcionário
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/employee/shift",
 *     summary="Retorna a jornada atualmente atribuída ao funcionário",
 *     description="Fornece os dados da jornada e da atribuição de turno em vigor, incluindo horários por dia útil.",
 *     tags={"Employee - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Jornada atual e dados da atribuição",
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
 *     schema="EmployeeShift",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string", example="Turno Manhã"),
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
 *     @OA\Property(property="break_minutes", type="integer", nullable=true, example=60)
 * )
 */
class EmployeeShiftDaySchema {}

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
 * ==========================================
 * Horas trabalhadas hoje
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/employee/worked-today",
 *     summary="Resumo das horas trabalhadas hoje pelo funcionário autenticado",
 *     description="Calcula pares IN/OUT do dia e apura horas descontando intervalos esperados/registrados.",
 *     tags={"Employee - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Resumo diário das horas trabalhadas",
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
 *     @OA\Property(property="worked_hours_decimal", type="number", format="float", example=8.0),
 *     @OA\Property(property="expected_break_minutes", type="integer", example=60),
 *     @OA\Property(property="break_seconds_deducted", type="integer", example=3600),
 *     @OA\Property(property="open_session", type="boolean", example=false),
 *     @OA\Property(
 *         property="details",
 *         type="object",
 *         @OA\Property(
 *             property="pairs",
 *             type="array",
 *             @OA\Items(ref="#/components/schemas/EmployeeWorkedTodayPair")
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
