<?php

namespace App\Swagger\Commercial;

/**
 * @OA\Tag(
 *     name="Commercial - Commissions",
 *     description="Comissões e bônus de afiliados. Restrito a super_admin. Comissão padrão: 20% por 6 meses. Bônus: 50€ a cada 5 clientes pagos no mesmo mês calendário."
 * )
 */
class Commissions {}

/**
 * @OA\Get(
 *     path="/v1/admin/commercial/commissions",
 *     summary="Lista comissões de afiliados",
 *     tags={"Commercial - Commissions"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="affiliate_id", in="query", @OA\Schema(type="string", format="uuid")),
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","approved","cancelled","paid"})),
 *
 *     @OA\Response(response=200, description="Lista paginada de comissões"),
 *     @OA\Response(response=403, description="Sem permissão para acessar comissões")
 * )
 */
class CommissionsIndex {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/commissions/{id}/approve",
 *     summary="Aprova uma comissão pendente",
 *     description="Fluxo manual/administrativo, usado enquanto não houver integração automática com evento de pagamento.",
 *     tags={"Commercial - Commissions"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Comissão aprovada")
 * )
 */
class CommissionsApprove {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/commissions/{id}/mark-paid",
 *     summary="Marca uma comissão como paga",
 *     description="Após marcar como paga, recalcula automaticamente o bônus mensal do afiliado (50€ a cada 5 clientes pagos no mês).",
 *     tags={"Commercial - Commissions"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Comissão paga")
 * )
 */
class CommissionsMarkPaid {}

/**
 * @OA\Get(
 *     path="/v1/admin/commercial/affiliate-bonuses",
 *     summary="Lista bônus mensais de afiliados",
 *     tags={"Commercial - Commissions"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="affiliate_id", in="query", @OA\Schema(type="string", format="uuid")),
 *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="month", in="query", @OA\Schema(type="integer")),
 *
 *     @OA\Response(response=200, description="Lista paginada de bônus por afiliado/mês")
 * )
 */
class AffiliateBonusesIndex {}
