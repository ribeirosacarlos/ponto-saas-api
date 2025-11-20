<?php

namespace App\Swagger\Admin\Employees;

/**
 * @OA\Get(
 *     path="/v1/admin/employees/{id}",
 *     summary="Exibe funcionário",
 *     tags={"Admin - Employees"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer", example=10)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="OK",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=10),
 *             @OA\Property(property="company_id", type="integer", example=3),
 *             @OA\Property(property="name", type="string", example="Maria Santos"),
 *             @OA\Property(property="email", type="string", example="maria@empresa.com"),
 *             @OA\Property(property="created_at", type="string", example="2025-02-10T14:32:20Z")
 *         )
 *     )
 * )
 */
class Show {}
