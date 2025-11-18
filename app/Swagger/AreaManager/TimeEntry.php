<?php

namespace App\Swagger\AreaManager;

/**
 * @OA\Tag(
 *     name="Area Manager - Time Entries",
 *     description="Consultas das batidas de ponto da equipe supervisionada"
 * )
 */
class TimeEntry {}


/**
 * ==========================================
 * Listar batidas da equipe
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/area-manager/team/entries",
 *     summary="Lista as batidas de ponto da equipe",
 *     description="Retorna entradas de ponto de todos os funcionários da equipe que o gestor pode visualizar.",
 *     tags={"Area Manager - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         required=false,
 *         description="Número da página para paginação",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada das batidas da equipe",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=30),
 *             @OA\Property(property="total", type="integer", example=120),
 *
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="integer", example=25),
 *                     @OA\Property(property="user_id", type="integer", example=12),
 *                     @OA\Property(property="clocked_at", type="string", example="2025-02-10T14:32:20Z"),
 *                     @OA\Property(property="type", type="string", example="in"),
 *                     @OA\Property(property="latitude", type="string", nullable=true, example="-23.550520"),
 *                     @OA\Property(property="longitude", type="string", nullable=true, example="-46.633308"),
 *                     @OA\Property(property="source", type="string", example="web"),
 *
 *                     @OA\Property(
 *                         property="user",
 *                         type="object",
 *                         description="Informações do funcionário",
 *                         @OA\Property(property="id", type="integer", example=12),
 *                         @OA\Property(property="name", type="string", example="João da Silva"),
 *                         @OA\Property(property="email", type="string", example="joao@empresa.com")
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=403,
 *         description="Usuário não autorizado a visualizar entradas da equipe"
 *     )
 * )
 */
class TimeEntryTeamEntries {}
