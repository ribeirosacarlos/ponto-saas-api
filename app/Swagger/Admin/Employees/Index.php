<?php

namespace App\Swagger\Admin\Employees;

/**
 * @OA\Get(
 *     path="/v1/admin/employees",
 *     summary="Lista todos os funcionários da empresa",
 *     description="Retorna uma lista paginada contendo todos os funcionários associados à empresa.",
 *     tags={"Admin - Employees"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         required=false,
 *         description="Página atual",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=20),
 *             @OA\Property(property="total", type="integer", example=45),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="integer", example=10),
 *                     @OA\Property(property="company_id", type="integer", example=3),
 *                     @OA\Property(property="name", type="string", example="Maria Santos"),
 *                     @OA\Property(property="email", type="string", example="maria@empresa.com"),
 *                     @OA\Property(property="created_at", type="string", example="2025-02-10T14:32:20Z")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class Index {}
