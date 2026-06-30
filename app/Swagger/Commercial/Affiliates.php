<?php

namespace App\Swagger\Commercial;

/**
 * @OA\Tag(
 *     name="Commercial - Affiliates",
 *     description="Gestão de afiliados, links de rastreamento e métricas. Restrito a super_admin e admin."
 * )
 */
class Affiliates {}

/**
 * @OA\Get(
 *     path="/v1/admin/commercial/affiliates",
 *     summary="Lista afiliados",
 *     tags={"Commercial - Affiliates"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200, description="Lista paginada de afiliados"),
 *     @OA\Response(response=403, description="Sem permissão para acessar afiliados")
 * )
 */
class AffiliatesIndex {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/affiliates",
 *     summary="Cria um afiliado",
 *     tags={"Commercial - Affiliates"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name","email","slug"},
 *
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="email", type="string", format="email"),
 *         @OA\Property(property="phone", type="string", nullable=true),
 *         @OA\Property(property="slug", type="string", description="Usado no link público /r/{slug}"),
 *         @OA\Property(property="commission_plan_id", type="string", format="uuid", nullable=true),
 *         @OA\Property(property="status", type="string", enum={"active","inactive"}, default="active")
 *     )),
 *
 *     @OA\Response(response=201, description="Afiliado criado"),
 *     @OA\Response(response=422, description="Slug duplicado ou dados inválidos")
 * )
 */
class AffiliatesStore {}

/**
 * @OA\Get(
 *     path="/v1/admin/commercial/affiliates/{affiliate}",
 *     summary="Exibe um afiliado",
 *     tags={"Commercial - Affiliates"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="affiliate", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Detalhes do afiliado")
 * )
 */
class AffiliatesShow {}

/**
 * @OA\Put(
 *     path="/v1/admin/commercial/affiliates/{affiliate}",
 *     summary="Atualiza um afiliado",
 *     tags={"Commercial - Affiliates"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="affiliate", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Afiliado atualizado")
 * )
 */
class AffiliatesUpdate {}

/**
 * @OA\Delete(
 *     path="/v1/admin/commercial/affiliates/{affiliate}",
 *     summary="Remove um afiliado",
 *     tags={"Commercial - Affiliates"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="affiliate", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Afiliado removido")
 * )
 */
class AffiliatesDestroy {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/affiliates/{id}/resend-invite",
 *     summary="Reenvia o convite de acesso ao afiliado",
 *     description="Gera um novo invite_code, atualiza invite_expires_at e reenvia o e-mail de convite. Restrito a super_admin e admin.",
 *     tags={"Commercial - Affiliates"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Convite reenviado com sucesso"),
 *     @OA\Response(response=404, description="Afiliado não encontrado")
 * )
 */
class AffiliatesResendInvite {}

/**
 * @OA\Get(
 *     path="/v1/admin/commercial/affiliates/{id}/metrics",
 *     summary="Métricas de performance do afiliado",
 *     tags={"Commercial - Affiliates"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(
 *         response=200,
 *         description="total_clicks, unique_clicks, total_leads, won_leads, conversion_rate_click_to_lead, conversion_rate_lead_to_customer, pending_commissions, paid_commissions, pending_bonus, paid_bonus"
 *     )
 * )
 */
class AffiliatesMetrics {}

/**
 * @OA\Get(
 *     path="/r/{slug}",
 *     summary="Link público de rastreamento do afiliado",
 *     description="Endpoint público (sem autenticação). Registra o clique (IP salvo apenas como hash SHA-256) e redireciona para a landing page do Jornafy com ?ref={slug}.",
 *     tags={"Commercial - Affiliates"},
 *
 *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
 *
 *     @OA\Response(response=302, description="Redirecionamento para a landing page")
 * )
 */
class AffiliatesRedirect {}

/**
 * @OA\Post(
 *     path="/v1/commercial/track-affiliate-click",
 *     summary="Registra um clique de afiliado (alternativa não-redirect)",
 *     description="Endpoint público (sem autenticação), pensado para frontends que não seguem o redirect HTTP de /r/{slug}.",
 *     tags={"Commercial - Affiliates"},
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"slug"},
 *
 *         @OA\Property(property="slug", type="string"),
 *         @OA\Property(property="landing_page", type="string", nullable=true),
 *         @OA\Property(property="utm_source", type="string", nullable=true),
 *         @OA\Property(property="utm_medium", type="string", nullable=true),
 *         @OA\Property(property="utm_campaign", type="string", nullable=true)
 *     )),
 *
 *     @OA\Response(response=201, description="Clique registrado"),
 *     @OA\Response(response=404, description="Afiliado não encontrado ou inativo")
 * )
 */
class AffiliatesTrackClick {}
