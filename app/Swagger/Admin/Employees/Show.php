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
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="OK",
 *         @OA\JsonContent(ref="#/components/schemas/EmployeeResource")
 *     )
 * )
 */
class Show {}
