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
 *             @OA\Property(property="role", type="string", enum={"admin","manager","area_manager","employee"})
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Criado",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=15),
 *             @OA\Property(property="company_id", type="integer", example=3),
 *             @OA\Property(property="name", type="string", example="João Silva"),
 *             @OA\Property(property="email", type="string", example="joao@empresa.com")
 *         )
 *     )
 * )
 */
class Store {}
