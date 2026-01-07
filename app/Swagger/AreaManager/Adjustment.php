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
 * Listar solicitações de ajuste (gestor/admin)
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/area-manager/adjustments",
 *     summary="Lista solicitações de ajuste da empresa",
 *     description="Lista ajustes de ponto da empresa do usuário autenticado (area_manager/manager/admin). Por padrão retorna apenas ajustes com status 'pending'. Suporta filtros e paginação.",
 *     tags={"Area Manager - Adjustments"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         required=false,
 *         description="Filtrar por status. Se não informado, default = pending.",
 *         @OA\Schema(type="string", enum={"pending","approved","rejected"}, example="pending")
 *     ),
 *
 *     @OA\Parameter(
 *         name="user_id",
 *         in="query",
 *         required=false,
 *         description="Filtrar pelos ajustes de um funcionário específico (UUID do usuário).",
 *         @OA\Schema(type="string", format="uuid", example="c16ad58b-99c1-431d-bbd7-c8c71eca33e7")
 *     ),
 *
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         required=false,
 *         description="Página da paginação",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         required=false,
 *         description="Quantidade de itens por página (se você implementar no backend). Se não, o Laravel usa o default do paginate().",
 *         @OA\Schema(type="integer", example=15)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada de ajustes",
 *         @OA\JsonContent(
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="string", format="uuid", example="019b9600-77ae-71dd-b912-4f26d4f2f354"),
 *                     @OA\Property(property="company_id", type="string", format="uuid", example="78f4ab91-296c-4fdb-a70c-c91415be1532"),
 *                     @OA\Property(property="user_id", type="string", format="uuid", example="c16ad58b-99c1-431d-bbd7-c8c71eca33e7"),
 *                     @OA\Property(property="approver_id", type="string", format="uuid", nullable=true, example=null),
 *                     @OA\Property(property="original_time", type="string", nullable=true, example=null),
 *                     @OA\Property(property="corrected_time", type="string", example="2026-01-06 13:03:00"),
 *                     @OA\Property(property="reason", type="string", nullable=true, example="Esqueci de marcar o ponto"),
 *                     @OA\Property(property="status", type="string", example="pending"),
 *                     @OA\Property(property="created_at", type="string", nullable=true, example="2026-01-07 01:09:31"),
 *                     @OA\Property(property="updated_at", type="string", nullable=true, example="2026-01-07 01:09:31"),
 *
 *                     @OA\Property(
 *                         property="user",
 *                         type="object",
 *                         nullable=true,
 *                         @OA\Property(property="id", type="string", format="uuid", example="c16ad58b-99c1-431d-bbd7-c8c71eca33e7"),
 *                         @OA\Property(property="name", type="string", example="João Silva"),
 *                         @OA\Property(property="email", type="string", example="joao@empresa.com")
 *                     ),
 *
 *                     @OA\Property(
 *                         property="approver",
 *                         type="object",
 *                         nullable=true,
 *                         @OA\Property(property="id", type="string", format="uuid", example="11111111-2222-3333-4444-555555555555"),
 *                         @OA\Property(property="name", type="string", example="Gestor Maria")
 *                     )
 *                 )
 *             ),
 *
 *             @OA\Property(
 *                 property="links",
 *                 type="object",
 *                 @OA\Property(property="first", type="string", nullable=true, example="https://api.jornafy.com/v1/area-manager/adjustments?page=1"),
 *                 @OA\Property(property="last", type="string", nullable=true, example="https://api.jornafy.com/v1/area-manager/adjustments?page=3"),
 *                 @OA\Property(property="prev", type="string", nullable=true, example=null),
 *                 @OA\Property(property="next", type="string", nullable=true, example="https://api.jornafy.com/v1/area-manager/adjustments?page=2")
 *             ),
 *
 *             @OA\Property(
 *                 property="meta",
 *                 type="object",
 *                 @OA\Property(property="current_page", type="integer", example=1),
 *                 @OA\Property(property="from", type="integer", nullable=true, example=1),
 *                 @OA\Property(property="last_page", type="integer", example=3),
 *                 @OA\Property(property="path", type="string", example="https://api.jornafy.com/v1/area-manager/adjustments"),
 *                 @OA\Property(property="per_page", type="integer", example=15),
 *                 @OA\Property(property="to", type="integer", nullable=true, example=15),
 *                 @OA\Property(property="total", type="integer", example=40)
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=401,
 *         description="Não autenticado"
 *     ),
 *
 *     @OA\Response(
 *         response=403,
 *         description="Usuário não autorizado"
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
