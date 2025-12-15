<?php

namespace App\Swagger\Admin\Employees;

/**
 * @OA\Post(
 *     path="/v1/admin/employees",
 *     summary="Cria um novo funcionário",
 *     tags={"Admin - Employees"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name","email","password"},
 *             @OA\Property(property="name", type="string", example="João Silva"),
 *             @OA\Property(property="email", type="string", example="joao@empresa.com"),
 *             @OA\Property(property="password", type="string", example="12345678"),
 *             @OA\Property(property="role", type="string", enum={"admin","manager","area_manager","employee"}),
 *             @OA\Property(property="shift_id", type="string", format="uuid", nullable=true, description="Opcional: força vínculo com jornada específica")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Criado",
 *         @OA\JsonContent(ref="#/components/schemas/EmployeeResource")
 *     )
 * )
 */
class Store {}

/**
 * @OA\Schema(
 *     schema="UserShiftResource",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="shift_id", type="string", format="uuid"),
 *     @OA\Property(property="start_date", type="string", format="date"),
 *     @OA\Property(property="end_date", type="string", format="date", nullable=true),
 *     @OA\Property(
 *         property="shift",
 *         type="object",
 *         @OA\Property(property="id", type="string", format="uuid"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="is_default", type="boolean")
 *     )
 * )
 */
class UserShiftResourceSchema {}

/**
 * @OA\Schema(
 *     schema="EmployeeResource",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="company_id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="email", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="user_shifts",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/UserShiftResource")
 *     )
 * )
 */
class EmployeeResourceSchema {}
