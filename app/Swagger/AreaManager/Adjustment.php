<?php

namespace App\Swagger\AreaManager;

/**
 * @OA\Tag(
 *     name="Area Manager - Adjustments",
 *     description="Aprovação e rejeição de ajustes feitos pelos funcionários"
 * )
 */
class Adjustment {}


/**
 * ==========================================
 * Aprovar ajuste
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/area-manager/adjustments/{id}/approve",
 *     summary="Aprova um ajuste solicitado pelo funcionário",
 *     description="O gestor aprova um ajuste pendente. Atualiza o status e registra o 'approver_id'.",
 *     tags={"Area Manager - Adjustments"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID do ajuste a ser aprovado",
 *         @OA\Schema(type="integer", example=5)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Ajuste aprovado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=5),
 *             @OA\Property(property="user_id", type="integer", example=12),
 *             @OA\Property(property="original_time", type="string", nullable=true, example=null),
 *             @OA\Property(property="corrected_time", type="string", example="2025-02-10 08:10:00"),
 *             @OA\Property(property="reason", type="string", example="Esqueci de bater o ponto"),
 *             @OA\Property(property="status", type="string", example="approved"),
 *             @OA\Property(property="approver_id", type="integer", example=7)
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=403,
 *         description="Usuário não autorizado"
 *     ),
 *
 *     @OA\Response(
 *         response=404,
 *         description="Ajuste não encontrado"
 *     )
 * )
 */
class AdjustmentApprove {}



/**
 * ==========================================
 * Rejeitar ajuste
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/area-manager/adjustments/{id}/reject",
 *     summary="Rejeita um ajuste solicitado pelo funcionário",
 *     description="O gestor rejeita um ajuste pendente. Atualiza o status e registra o 'approver_id'.",
 *     tags={"Area Manager - Adjustments"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID do ajuste a ser rejeitado",
 *         @OA\Schema(type="integer", example=5)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Ajuste rejeitado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=5),
 *             @OA\Property(property="user_id", type="integer", example=12),
 *             @OA\Property(property="original_time", type="string", nullable=true, example=null),
 *             @OA\Property(property="corrected_time", type="string", example="2025-02-10 08:10:00"),
 *             @OA\Property(property="reason", type="string", example="Esqueci de bater o ponto"),
 *             @OA\Property(property="status", type="string", example="rejected"),
 *             @OA\Property(property="approver_id", type="integer", example=7)
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=403,
 *         description="Usuário não autorizado"
 *     ),
 *
 *     @OA\Response(
 *         response=404,
 *         description="Ajuste não encontrado"
 *     )
 * )
 */
class AdjustmentReject {}
