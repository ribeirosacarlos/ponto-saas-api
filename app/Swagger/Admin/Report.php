<?php

namespace App\Swagger\Admin;

/**
 * @OA\Tag(
 *     name="Admin - Reports",
 *     description="Relatórios administrativos"
 * )
 */
class Report {}



/**
 * ==========================================
 * Relatório de Batidas (Time Report)
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/admin/reports/time",
 *     summary="Relatório de batidas por período",
 *     description="Retorna todas as batidas de ponto dentro de um intervalo de datas, incluindo os dados do usuário.",
 *     tags={"Admin - Reports"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="start",
 *         in="query",
 *         required=true,
 *         description="Data inicial (YYYY-MM-DD)",
 *         @OA\Schema(type="string", example="2025-02-01")
 *     ),
 *
 *     @OA\Parameter(
 *         name="end",
 *         in="query",
 *         required=true,
 *         description="Data final (YYYY-MM-DD)",
 *         @OA\Schema(type="string", example="2025-02-28")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista de batidas no período fornecido",
 *         @OA\JsonContent(
 *             type="array",
 *             @OA\Items(
 *                 @OA\Property(property="id", type="integer", example=12),
 *                 @OA\Property(property="user_id", type="integer", example=7),
 *                 @OA\Property(property="clocked_at", type="string", example="2025-02-10T09:00:00Z"),
 *                 @OA\Property(property="type", type="string", example="in"),
 *                 @OA\Property(property="latitude", type="string", nullable=true, example="-23.550520"),
 *                 @OA\Property(property="longitude", type="string", nullable=true, example="-46.633308"),
 *                 @OA\Property(property="source", type="string", example="web"),
 *
 *                 @OA\Property(
 *                     property="user",
 *                     type="object",
 *                     description="Informações do funcionário",
 *                     @OA\Property(property="id", type="integer", example=7),
 *                     @OA\Property(property="name", type="string", example="Carlos Alberto"),
 *                     @OA\Property(property="email", type="string", example="carlos@empresa.com")
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Erro de validação (datas ausentes ou inválidas)"
 *     )
 * )
 */
class ReportTime {}
