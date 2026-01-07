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
 *     description="Retorna entradas de ponto da empresa do gestor/admin. Por padrão lista tudo (in e out). Suporta filtros e paginação.",
 *     tags={"Area Manager - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="type",
 *         in="query",
 *         required=false,
 *         description="Filtra pelo tipo de batida. Se omitido, retorna todos.",
 *         @OA\Schema(type="string", enum={"in","out"}, example="in")
 *     ),
 *
 *     @OA\Parameter(
 *         name="user_id",
 *         in="query",
 *         required=false,
 *         description="Filtra por funcionário específico (UUID).",
 *         @OA\Schema(type="string", format="uuid", example="c16ad58b-99c1-431d-bbd7-c8c71eca33e7")
 *     ),
 *
 *     @OA\Parameter(
 *         name="date_from",
 *         in="query",
 *         required=false,
 *         description="Data inicial (inclusive). Aceita qualquer formato parseável pelo Laravel, preferível ISO.",
 *         @OA\Schema(type="string", format="date-time", example="2026-01-01T00:00:00Z")
 *     ),
 *
 *     @OA\Parameter(
 *         name="date_to",
 *         in="query",
 *         required=false,
 *         description="Data final (inclusive).",
 *         @OA\Schema(type="string", format="date-time", example="2026-01-31T23:59:59Z")
 *     ),
 *
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         required=false,
 *         description="Número da página para paginação",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         required=false,
 *         description="Quantidade de itens por página (máx 200). Default 30.",
 *         @OA\Schema(type="integer", example=30)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada das batidas da equipe",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=30),
 *             @OA\Property(property="total", type="integer", example=120),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="string", format="uuid", example="019b9600-77ae-71dd-b912-4f26d4f2f354"),
 *                     @OA\Property(property="user_id", type="string", format="uuid", example="c16ad58b-99c1-431d-bbd7-c8c71eca33e7"),
 *                     @OA\Property(property="company_id", type="string", format="uuid", example="78f4ab91-296c-4fdb-a70c-c91415be1532"),
 *                     @OA\Property(property="clocked_at", type="string", example="2026-01-06T13:03:00Z"),
 *                     @OA\Property(property="type", type="string", example="in"),
 *                     @OA\Property(property="latitude", type="string", nullable=true, example="-23.550520"),
 *                     @OA\Property(property="longitude", type="string", nullable=true, example="-46.633308"),
 *                     @OA\Property(property="source", type="string", example="web"),
 *
 *                     @OA\Property(
 *                         property="user",
 *                         type="object",
 *                         description="Informações do funcionário",
 *                         @OA\Property(property="id", type="string", format="uuid", example="c16ad58b-99c1-431d-bbd7-c8c71eca33e7"),
 *                         @OA\Property(property="name", type="string", example="João da Silva"),
 *                         @OA\Property(property="email", type="string", example="joao@empresa.com")
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=401,
 *         description="Não autenticado"
 *     ),
 *
 *     @OA\Response(
 *         response=403,
 *         description="Usuário não autorizado a visualizar entradas da equipe"
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Erro de validação (parâmetros inválidos)"
 *     )
 * )
 */
class TimeEntryTeamEntries {}
