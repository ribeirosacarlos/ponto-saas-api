<?php

namespace App\Swagger\Admin\Employees;

/**
 * @OA\Post(
 *     path="/v1/admin/employees",
 *     summary="Cria um novo funcionário e dispara um convite",
 *     description="O convite chega por e-mail (Resend) e pode conter link ou senha provisória, tudo via job em fila.",
 *     tags={"Admin - Employees"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *
 *         @OA\JsonContent(
 *             required={"name","email"},
 *
 *             @OA\Property(property="name", type="string", example="João Silva"),
 *             @OA\Property(property="email", type="string", example="joao@empresa.com"),
 *             @OA\Property(property="role", type="string", enum={"admin","manager","area_manager","employee"}),
 *             @OA\Property(property="shift_id", type="string", format="uuid", nullable=true, description="Opcional: força vínculo com jornada específica")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Criado",
 *
 *         @OA\JsonContent(ref="#/components/schemas/EmployeeResource")
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Erro de validação de e-mail",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="message", type="string", example="The given data was invalid."),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="email", type="array",
 *
 *                     @OA\Items(type="string", example="The email has already been taken.")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class Store {}

/**
 * @OA\Schema(
 *     schema="UserShiftResource",
 *
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
 *
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="company_id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="email", type="string"),
 *     @OA\Property(property="area_id", type="string", format="uuid", nullable=true),
 *     @OA\Property(property="must_change_password", type="boolean"),
 *     @OA\Property(property="role", type="object", nullable=true),
 *     @OA\Property(property="roles", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="area", type="object", nullable=true),
 *     @OA\Property(property="managed_areas", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="user_shifts",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/UserShiftResource")
 *     )
 * )
 */
class EmployeeResourceSchema {}
