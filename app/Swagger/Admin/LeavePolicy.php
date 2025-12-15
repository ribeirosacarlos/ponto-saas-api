<?php

namespace App\Swagger\Admin;

/**
 * @OA\Tag(
 *     name="Admin - Leave Policies",
 *     description="Políticas de férias por empresa"
 * )
 */
class LeavePolicy {}

/**
 * @OA\Get(
 *     path="/v1/admin/leave-policies",
 *     summary="Lista políticas de férias",
 *     tags={"Admin - Leave Policies"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Lista paginada")
 * )
 */
class LeavePolicyIndex {}

/**
 * @OA\Post(
 *     path="/v1/admin/leave-policies",
 *     summary="Cria nova política",
 *     tags={"Admin - Leave Policies"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name"},
 *             @OA\Property(property="name", type="string", example="Política Padrão Espanha"),
 *             @OA\Property(property="days_per_year", type="number", example=30),
 *             @OA\Property(property="counting_method", type="string", enum={"calendar_days","working_days"})
 *         )
 *     ),
 *     @OA\Response(response=201, description="Criado")
 * )
 */
class LeavePolicyStore {}

/**
 * @OA\Put(
 *     path="/v1/admin/leave-policies/{id}",
 *     summary="Atualiza política",
 *     tags={"Admin - Leave Policies"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Atualizado")
 * )
 */
class LeavePolicyUpdate {}

/**
 * @OA\Delete(
 *     path="/v1/admin/leave-policies/{id}",
 *     summary="Remove política",
 *     tags={"Admin - Leave Policies"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Removido")
 * )
 */
class LeavePolicyDestroy {}
