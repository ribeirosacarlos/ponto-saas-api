<?php

namespace App\Swagger\Admin\Employees;

/**
 * @OA\Put(
 *     path="/v1/admin/employees/{id}",
 *     summary="Atualiza funcionário",
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
 *     @OA\RequestBody(
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="Maria Silva"),
 *             @OA\Property(property="email", type="string", example="maria.silva@empresa.com"),
 *             @OA\Property(property="password", type="string", example="novaSenha123")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Atualizado",
 *     )
 * )
 */
class Update {}
