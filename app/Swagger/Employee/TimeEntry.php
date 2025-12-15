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
