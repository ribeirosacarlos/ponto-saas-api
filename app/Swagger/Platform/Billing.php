<?php

namespace App\Swagger\Platform;

/**
 * @OA\Tag(
 *     name="Platform - Billing",
 *     description="Cobrança e assinaturas gerenciadas pela plataforma para o super admin"
 * )
 */
class Billing {}

/**
 * @OA\Get(
 *     path="/v1/platform/billing/plans",
 *     summary="Lista planos disponíveis",
 *     tags={"Platform - Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Planos paginados",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=20),
 *             @OA\Property(property="total", type="integer", example=3),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/PlatformBillingPlanResource")
 *             )
 *         )
 *     )
 * )
 */
class BillingPlansIndex {}

/**
 * @OA\Post(
 *     path="/v1/platform/billing/plans",
 *     summary="Cria um novo plano",
 *     tags={"Platform - Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/PlatformBillingPlanPayload")
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Plano criado",
 *         @OA\JsonContent(ref="#/components/schemas/PlatformBillingPlanResource")
 *     )
 * )
 */
class BillingPlansStore {}

/**
 * @OA\Get(
 *     path="/v1/platform/billing/plans/{plan}",
 *     summary="Exibe um plano",
 *     tags={"Platform - Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="plan", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(
 *         response=200,
 *         description="Plano",
 *         @OA\JsonContent(ref="#/components/schemas/PlatformBillingPlanResource")
 *     )
 * )
 */
class BillingPlansShow {}

/**
 * @OA\Patch(
 *     path="/v1/platform/billing/plans/{plan}",
 *     summary="Atualiza um plano",
 *     tags={"Platform - Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="plan", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/PlatformBillingPlanPayload")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Plano atualizado",
 *         @OA\JsonContent(ref="#/components/schemas/PlatformBillingPlanResource")
 *     )
 * )
 */
class BillingPlansUpdate {}

/**
 * @OA\Get(
 *     path="/v1/platform/billing/companies/{company}/subscription",
 *     summary="Exibe assinatura de uma empresa",
 *     tags={"Platform - Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(
 *         response=200,
 *         description="Assinatura",
 *         @OA\JsonContent(ref="#/components/schemas/PlatformBillingSubscriptionResource")
 *     )
 * )
 */
class BillingCompanySubscriptionShow {}

/**
 * @OA\Patch(
 *     path="/v1/platform/billing/companies/{company}/subscription",
 *     summary="Atualiza assinatura da empresa",
 *     tags={"Platform - Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/PlatformBillingSubscriptionPayload")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Assinatura atualizada",
 *         @OA\JsonContent(ref="#/components/schemas/PlatformBillingSubscriptionResource")
 *     )
 * )
 */
class BillingCompanySubscriptionUpdate {}


/**
 * @OA\Schema(
 *     schema="PlatformBillingPlanResource",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="price_cents", type="integer"),
 *     @OA\Property(property="currency", type="string"),
 *     @OA\Property(property="billing_interval", type="string", enum={"month","year","one_time"}, nullable=true),
 *     @OA\Property(property="trial_days", type="integer"),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="sort_order", type="integer"),
 *     @OA\Property(
 *         property="features",
 *         type="object",
 *         additionalProperties=@OA\Schema(
 *             schema="PlatformBillingPlanFeatureValue",
 *             type="boolean"
 *         )
 *     ),
 *     @OA\Property(
 *         property="quotas",
 *         type="object",
 *         additionalProperties=@OA\Schema(
 *             schema="PlatformBillingPlanQuotaValue",
 *             type="integer"
 *         )
 *     )
 * )
 */
class PlatformBillingPlanResourceDoc {}


/**
 * @OA\Schema(
 *     schema="PlatformBillingPlanPayload",
 *     required={"name","slug","price_cents","currency"},
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="price_cents", type="integer"),
 *     @OA\Property(property="currency", type="string"),
 *     @OA\Property(property="billing_interval", type="string", enum={"month","year","one_time"}, nullable=true),
 *     @OA\Property(property="trial_days", type="integer", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", nullable=true),
 *     @OA\Property(property="sort_order", type="integer", nullable=true),
 *     @OA\Property(
 *         property="features",
 *         type="object",
 *         additionalProperties=@OA\Schema(
 *             schema="PlatformBillingPlanPayloadFeatureValue",
 *             type="boolean"
 *         ),
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="quotas",
 *         type="object",
 *         additionalProperties=@OA\Schema(
 *             schema="PlatformBillingPlanPayloadQuotaValue",
 *             type="integer"
 *         ),
 *         nullable=true
 *     )
 * )
 */
class PlatformBillingPlanPayloadDoc {}


/**
 * @OA\Schema(
 *     schema="PlatformBillingSubscriptionResource",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="company_id", type="string", format="uuid"),
 *     @OA\Property(property="plan", ref="#/components/schemas/PlatformBillingPlanResource"),
 *     @OA\Property(property="status", type="string", enum={"trialing","active","past_due","canceled"}),
 *     @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="current_period_start", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="current_period_end", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="canceled_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="past_due_since", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="grace_period_days", type="integer"),
 *     @OA\Property(property="stripe_customer_id", type="string", nullable=true),
 *     @OA\Property(property="stripe_subscription_id", type="string", nullable=true),
 *     @OA\Property(property="metadata", type="object", nullable=true, @OA\AdditionalProperties(@OA\Schema(
 *         schema="PlatformBillingSubscriptionMetadataValue",
 *         type="string"
 *     )))
 * )
 */
class PlatformBillingSubscriptionResourceDoc {}


/**
 * @OA\Schema(
 *     schema="PlatformBillingSubscriptionPayload",
 *     @OA\Property(property="plan_id", type="string", format="uuid", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"trialing","active","past_due","canceled"}, nullable=true),
 *     @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="current_period_start", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="current_period_end", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="canceled_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="past_due_since", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="grace_period_days", type="integer", nullable=true),
 *     @OA\Property(property="stripe_customer_id", type="string", nullable=true),
 *     @OA\Property(property="stripe_subscription_id", type="string", nullable=true),
 *     @OA\Property(property="metadata", type="object", nullable=true, @OA\AdditionalProperties(@OA\Schema(
 *         schema="PlatformBillingSubscriptionPayloadMetadataValue",
 *         type="string"
 *     )))
 * )
 */
class PlatformBillingSubscriptionPayloadDoc {}
