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
