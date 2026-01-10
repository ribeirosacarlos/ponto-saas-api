<?php

namespace App\Swagger\Billing;

/**
 * @OA\Tag(
 *     name="Billing",
 *     description="Rotas públicas de planos, sessões de checkout e portal de cobrança"
 * )
 */
class Billing {}

/**
 * @OA\Get(
 *     path="/v1/billing/plans",
 *     summary="Lista os planos ativos disponíveis para assinatura",
 *     tags={"Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Lista de planos",
 *         @OA\JsonContent(
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/BillingPlanResource")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=500, description="Erro interno")
 * )
 */
class BillingPlansIndex {}

/**
 * @OA\Post(
 *     path="/v1/billing/checkout-session",
 *     summary="Cria sessão do Stripe Checkout para um plano",
 *     tags={"Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/BillingCheckoutSessionRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="URL do Checkout Session",
 *         @OA\JsonContent(ref="#/components/schemas/BillingCheckoutSessionResponse")
 *     ),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=422, description="Dados inválidos ou plano sem price_id"),
 *     @OA\Response(response=500, description="Erro ao gerar sessão de checkout")
 * )
 */
class BillingCheckoutSession {}

/**
 * @OA\Post(
 *     path="/v1/public/billing/checkout-session",
 *     summary="Cria sessão pública do Stripe Checkout para o site",
 *     description="Endpoint público utilizado pelo site após cadastro. Protegido por rate limit e honeypot.",
 *     tags={"Billing"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/PublicBillingCheckoutSessionRequest")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="URL pública do Stripe Checkout Session",
 *         @OA\JsonContent(ref="#/components/schemas/BillingCheckoutSessionResponse")
 *     ),
 *     @OA\Response(response=403, description="Empresa bloqueada"),
 *     @OA\Response(response=404, description="Empresa ou plano não encontrado"),
 *     @OA\Response(response=422, description="Dados inválidos, plano gratuito ou sem preço configurado"),
 *     @OA\Response(response=429, description="Limite de requisições excedido"),
 *     @OA\Response(response=500, description="Erro ao gerar sessão de checkout")
 * )
 */
class PublicBillingCheckoutSession {}

/**
 * @OA\Post(
 *     path="/v1/billing/portal",
 *     summary="Cria link para o Stripe Billing Portal",
 *     tags={"Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Link do portal",
 *         @OA\JsonContent(ref="#/components/schemas/BillingPortalResponse")
 *     ),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=422, description="Stripe customer não encontrado"),
 *     @OA\Response(response=500, description="Erro ao abrir o portal")
 * )
 */
class BillingPortal {}

/**
 * @OA\Post(
 *     path="/v1/billing/stripe/webhook",
 *     summary="Recebe eventos da Stripe para atualizar assinaturas",
 *     tags={"Billing"},
 *     description="A Stripe envia os eventos definidos no webhook. A assinatura é validada pelo segredo configurado.",
 *     @OA\RequestBody(
 *         required=true,
 *         description="Payload enviado pela Stripe",
 *         @OA\MediaType(mediaType="application/json")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Evento processado"
 *     ),
 *     @OA\Response(response=400, description="Webhook inválido ou sem assinatura"),
 *     @OA\Response(response=500, description="Erro interno ao processar evento")
 * )
 */
class StripeWebhook {}
