<?php

namespace App\Swagger\AreaManager;

/**
 * @OA\Tag(
 *     name="Area Manager - Adjustments",
 *     description="Visualizar, aprovar e rejeitar ajustes pendentes"
 * )
 */
class Adjustment {}

/**
 * ==========================================
 * Listar ajustes pendentes
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/area-manager/adjustments",
 *     summary="Lista ajustes via time_entries",
 *     description="Retorna os time entries que possuem ajustes pendentes na empresa do usuário autenticado.",
 *     tags={"Area Manager - Adjustments"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         description="Filtrar por status (pending, approved, rejected). Default = pending.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="user_id",
 *         in="query",
 *         description="UUID do funcionário",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         @OA\Schema(type="integer", example=15)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada de time entries com ajustes",
 *         @OA\JsonContent(
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="string", format="uuid"),
 *                     @OA\Property(property="user_id", type="string", format="uuid"),
 *                     @OA\Property(property="clocked_at", type="string", format="date-time"),
 *                     @OA\Property(property="type", type="string"),
 *                     @OA\Property(property="source", type="string"),
 *                     @OA\Property(property="adjustment_status", type="string", example="pending"),
 *                     @OA\Property(property="adjustment_reason", type="string"),
 *                     @OA\Property(property="proposed_clocked_at", type="string", format="date-time"),
 *                     @OA\Property(property="proposed_type", type="string"),
 *                     @OA\Property(property="proposed_latitude", type="number"),
 *                     @OA\Property(property="proposed_longitude", type="number"),
 *                     @OA\Property(property="user", type="object",
 *                         @OA\Property(property="id", type="string", format="uuid"),
 *                         @OA\Property(property="name", type="string"),
 *                         @OA\Property(property="email", type="string")
 *                     )
 *                 )
 *             ),
 *             @OA\Property(property="meta", type="object"),
 *             @OA\Property(property="links", type="object")
 *         )
 *     )
 * )
 */
class AdjustmentIndex {}

/**
 * ==========================================
 * Aprovar ajuste
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/admin/time-entries/{timeEntry}/adjustment/approve",
 *     summary="Aprova um ajuste pendente",
 *     description="Aplica os valores propostos ao time entry e marca o ajuste como aprovado.",
 *     tags={"Area Manager - Adjustments"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="timeEntry",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\JsonContent(
 *             @OA\Property(property="review_reason", type="string", example="Confere")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Ajuste aprovado",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", format="uuid"),
 *             @OA\Property(property="clocked_at", type="string", format="date-time"),
 *             @OA\Property(property="type", type="string"),
 *             @OA\Property(property="adjustment_status", type="string", example="approved"),
 *             @OA\Property(property="adjustment_review_reason", type="string"),
 *             @OA\Property(property="adjustment_reviewed_at", type="string", format="date-time")
 *         )
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
 *     path="/v1/admin/time-entries/{timeEntry}/adjustment/reject",
 *     summary="Rejeita um ajuste pendente",
 *     description="Marca o ajuste como rejeitado e mantém os dados oficiais.",
 *     tags={"Area Manager - Adjustments"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="timeEntry",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\JsonContent(
 *             @OA\Property(property="review_reason", type="string", example="Rejeitado")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Ajuste rejeitado",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", format="uuid"),
 *             @OA\Property(property="adjustment_status", type="string", example="rejected"),
 *             @OA\Property(property="adjustment_review_reason", type="string"),
 *             @OA\Property(property="adjustment_reviewed_at", type="string", format="date-time")
 *         )
 *     )
 * )
 */
class AdjustmentReject {}
