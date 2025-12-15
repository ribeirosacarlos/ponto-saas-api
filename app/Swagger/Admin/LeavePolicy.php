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
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="data", type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="string", format="uuid"),
 *                     @OA\Property(property="name", type="string", example="Política Padrão Espanha"),
 *                     @OA\Property(property="days_per_year", type="number", example=30),
 *                     @OA\Property(property="accrual_rate_per_month", type="number", example=2.5),
 *                     @OA\Property(property="counting_method", type="string", example="calendar_days")
 *                 )
 *             )
 *         )
 *     )
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
 *     @OA\Response(
 *         response=201,
 *         description="Criado",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", format="uuid"),
 *             @OA\Property(property="name", type="string", example="Política Padrão Espanha")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validação",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="The given data was invalid."),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="name", type="array",
 *                     @OA\Items(type="string", example="O campo name é obrigatório.")
 *                 )
 *             )
 *         )
 *     )
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
 *     @OA\Response(response=200, description="Atualizado"),
 *     @OA\Response(response=404, description="Não encontrado")
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
 *     @OA\Response(response=200, description="Removido"),
 *     @OA\Response(response=404, description="Não encontrado")
 * )
 */
class LeavePolicyDestroy {}
