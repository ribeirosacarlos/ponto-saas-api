<?php

namespace App\Swagger\Commercial;

/**
 * @OA\Tag(
 *     name="Commercial - Affiliate Portal",
 *     description="Autenticação e área logada do afiliado (papel 'affiliate'). O afiliado só enxerga seus próprios leads, comissões e bônus."
 * )
 */
class AffiliatePortal {}

/**
 * @OA\Post(
 *     path="/v1/auth/affiliate/login",
 *     summary="Login do afiliado",
 *     description="Endpoint público. Retorna um token Sanctum vinculado ao registro CommercialAffiliate (não a um User).",
 *     tags={"Commercial - Affiliate Portal"},
 *
 *     @OA\RequestBody(
 *         required=true,
 *
 *         @OA\JsonContent(
 *             required={"email","password"},
 *
 *             @OA\Property(property="email", type="string", format="email"),
 *             @OA\Property(property="password", type="string")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Login efetuado",
 *         @OA\JsonContent(
 *             @OA\Property(property="affiliate", type="object"),
 *             @OA\Property(property="token", type="string")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Credenciais inválidas"),
 *     @OA\Response(response=403, description="Conta de afiliado inativa")
 * )
 */
class AffiliateLogin {}

/**
 * @OA\Get(
 *     path="/v1/affiliate/me",
 *     summary="Dados do afiliado autenticado",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200, description="Afiliado autenticado, incluindo plano de comissão")
 * )
 */
class AffiliateMe {}

/**
 * @OA\Post(
 *     path="/v1/affiliate/logout",
 *     summary="Logout do afiliado",
 *     description="Revoga o token Sanctum atual do afiliado.",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200, description="Logout efetuado")
 * )
 */
class AffiliateLogout {}

/**
 * @OA\Post(
 *     path="/v1/invites/affiliate/accept",
 *     summary="Aceita convite de afiliado e define senha",
 *     description="Endpoint público. Valida o invite_code (hash SHA-256) enviado por e-mail e expiração (invite_expires_at).",
 *     tags={"Commercial - Affiliate Portal"},
 *
 *     @OA\RequestBody(
 *         required=true,
 *
 *         @OA\JsonContent(
 *             required={"invite_code","password","password_confirmation"},
 *
 *             @OA\Property(property="invite_code", type="string"),
 *             @OA\Property(property="password", type="string", minLength=8),
 *             @OA\Property(property="password_confirmation", type="string")
 *         )
 *     ),
 *
 *     @OA\Response(response=200, description="Senha criada com sucesso"),
 *     @OA\Response(response=422, description="Código de convite inválido ou expirado")
 * )
 */
class AffiliateInviteAccept {}

/**
 * @OA\Get(
 *     path="/v1/affiliate-portal/me",
 *     summary="Perfil do afiliado (portal)",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200, description="Dados do afiliado autenticado, incluindo plano de comissão")
 * )
 */
class PortalMe {}

/**
 * @OA\Get(
 *     path="/v1/affiliate-portal/dashboard",
 *     summary="Métricas do afiliado (portal)",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="total_clicks, unique_clicks, total_leads, won_leads, conversion_rate_click_to_lead, conversion_rate_lead_to_customer, pending_commissions, paid_commissions, pending_bonus, paid_bonus"
 *     )
 * )
 */
class PortalDashboard {}

/**
 * @OA\Get(
 *     path="/v1/affiliate-portal/leads",
 *     summary="Lista os leads do próprio afiliado",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"new","in_progress","demo_scheduled","proposal_sent","won","lost","nurturing"})),
 *     @OA\Parameter(name="priority", in="query", @OA\Schema(type="string", enum={"low","medium","high","very_high"})),
 *     @OA\Parameter(name="current_step_id", in="query", @OA\Schema(type="string", format="uuid")),
 *     @OA\Parameter(name="search", in="query", description="Busca em company_name, contact_name, email, phone", @OA\Schema(type="string")),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *
 *     @OA\Response(response=200, description="Lista paginada de leads do afiliado")
 * )
 */
class PortalLeadsIndex {}

/**
 * @OA\Post(
 *     path="/v1/affiliate-portal/leads",
 *     summary="Cria um lead indicado pelo afiliado",
 *     description="affiliate_id é definido automaticamente a partir do afiliado autenticado. Detecta (sem bloquear) possíveis duplicados.",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *
 *         @OA\JsonContent(
 *             required={"company_name"},
 *
 *             @OA\Property(property="company_name", type="string"),
 *             @OA\Property(property="contact_name", type="string", nullable=true),
 *             @OA\Property(property="email", type="string", format="email", nullable=true),
 *             @OA\Property(property="phone", type="string", nullable=true),
 *             @OA\Property(property="whatsapp", type="string", nullable=true),
 *             @OA\Property(property="website", type="string", nullable=true),
 *             @OA\Property(property="country", type="string", nullable=true),
 *             @OA\Property(property="city", type="string", nullable=true),
 *             @OA\Property(property="segment", type="string", nullable=true),
 *             @OA\Property(property="employees_count", type="integer", nullable=true),
 *             @OA\Property(property="source", type="string", nullable=true),
 *             @OA\Property(property="current_step_id", type="string", format="uuid", nullable=true),
 *             @OA\Property(property="priority", type="string", enum={"low","medium","high","very_high"}, default="medium"),
 *             @OA\Property(property="general_notes", type="string", nullable=true)
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Lead criado. Inclui aviso de possíveis duplicados (duplicate_warning, possible_duplicates)"
 *     ),
 *     @OA\Response(response=422, description="Erro de validação")
 * )
 */
class PortalLeadsStore {}

/**
 * @OA\Get(
 *     path="/v1/affiliate-portal/leads/{id}",
 *     summary="Exibe um lead do próprio afiliado",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Detalhes do lead, incluindo notas e histórico de etapas"),
 *     @OA\Response(response=404, description="Lead não encontrado ou não pertence a este afiliado")
 * )
 */
class PortalLeadsShow {}

/**
 * @OA\Put(
 *     path="/v1/affiliate-portal/leads/{id}",
 *     summary="Atualiza um lead do próprio afiliado",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Lead atualizado"),
 *     @OA\Response(response=404, description="Lead não encontrado ou não pertence a este afiliado"),
 *     @OA\Response(response=422, description="Erro de validação")
 * )
 */
class PortalLeadsUpdate {}

/**
 * @OA\Post(
 *     path="/v1/affiliate-portal/leads/{id}/notes",
 *     summary="Adiciona uma observação a um lead do afiliado",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"note"}, @OA\Property(property="note", type="string"))),
 *
 *     @OA\Response(response=201, description="Nota criada")
 * )
 */
class PortalLeadsAddNote {}

/**
 * @OA\Post(
 *     path="/v1/affiliate-portal/leads/{id}/next-action",
 *     summary="Define a próxima ação de um lead do afiliado",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"next_action_type","next_action_at"},
 *
 *         @OA\Property(property="next_action_type", type="string"),
 *         @OA\Property(property="next_action_at", type="string", format="date-time")
 *     )),
 *
 *     @OA\Response(response=200, description="Próxima ação registrada")
 * )
 */
class PortalLeadsNextAction {}

/**
 * @OA\Post(
 *     path="/v1/affiliate-portal/leads/{id}/move-step",
 *     summary="Move um lead do afiliado para outra etapa",
 *     description="Cria um registro em lead_step_logs.",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"step_id"},
 *
 *         @OA\Property(property="step_id", type="string", format="uuid"),
 *         @OA\Property(property="note", type="string", nullable=true),
 *         @OA\Property(property="scheduled_at", type="string", format="date-time", nullable=true)
 *     )),
 *
 *     @OA\Response(response=200, description="Etapa atualizada")
 * )
 */
class PortalLeadsMoveStep {}

/**
 * @OA\Post(
 *     path="/v1/affiliate-portal/leads/{id}/mark-won",
 *     summary="Marca um lead do afiliado como convertido",
 *     description="Se base_amount for informado, gera as comissões recorrentes do afiliado (padrão: 20% por 6 meses).",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\RequestBody(@OA\JsonContent(
 *
 *         @OA\Property(property="customer_id", type="string", format="uuid", nullable=true),
 *         @OA\Property(property="base_amount", type="number", format="float", nullable=true)
 *     )),
 *
 *     @OA\Response(response=200, description="Lead convertido")
 * )
 */
class PortalLeadsMarkWon {}

/**
 * @OA\Post(
 *     path="/v1/affiliate-portal/leads/{id}/mark-lost",
 *     summary="Marca um lead do afiliado como perdido",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="lost_reason", type="string", nullable=true))),
 *
 *     @OA\Response(response=200, description="Lead marcado como perdido")
 * )
 */
class PortalLeadsMarkLost {}

/**
 * @OA\Get(
 *     path="/v1/affiliate-portal/commissions",
 *     summary="Lista as comissões do próprio afiliado",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","approved","cancelled","paid"})),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *
 *     @OA\Response(response=200, description="Lista paginada de comissões")
 * )
 */
class PortalCommissions {}

/**
 * @OA\Get(
 *     path="/v1/affiliate-portal/bonuses",
 *     summary="Lista os bônus mensais do próprio afiliado",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="month", in="query", @OA\Schema(type="integer")),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *
 *     @OA\Response(response=200, description="Lista paginada de bônus (50€ a cada 5 clientes pagos no mesmo mês calendário)")
 * )
 */
class PortalBonuses {}

/**
 * @OA\Get(
 *     path="/v1/affiliate-portal/steps",
 *     summary="Lista etapas do funil comercial (visão do afiliado)",
 *     tags={"Commercial - Affiliate Portal"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200, description="Lista de etapas ordenadas por position")
 * )
 */
class PortalSteps {}
