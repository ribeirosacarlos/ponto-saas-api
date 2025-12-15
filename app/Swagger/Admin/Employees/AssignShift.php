<?php

namespace App\Swagger\Admin\Employees;

/**
 * @OA\Post(
 *     path="/v1/admin/employees/{id}/shift",
 *     summary="Atribui uma jornada ao colaborador",
 *     tags={"Admin - Employees"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"shift_id"},
 *             @OA\Property(property="shift_id", type="string", format="uuid"),
 *             @OA\Property(property="start_date", type="string", format="date", nullable=true, description="Data efetiva (default: hoje)")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Associação criada",
 *         @OA\JsonContent(ref="#/components/schemas/UserShiftResource")
 *     )
 * )
 */
class AssignShift {}
