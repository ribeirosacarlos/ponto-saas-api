<?php

namespace App\Swagger\Admin\Employees;

/**
 * @OA\Delete(
 *     path="/v1/admin/employees/{id}",
 *     summary="Remove funcionário",
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
 *         description="Removido",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Deletado")
 *         )
 *     )
 * )
 */
class Destroy {}
