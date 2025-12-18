<?php

namespace App\Swagger\Admin;

/**
 * @OA\Tag(
 *     name="Admin - Billing",
 *     description="Gestão interna de planos e assinaturas"
 * )
 */
class Billing {}

/**
 * @OA\Get(
 *     path="/v1/admin/billing/plans",
 *     summary="Lista planos disponíveis",
 *     tags={"Admin - Billing"},
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
 *                 @OA\Items(ref="#/components/schemas/AdminBillingPlanResource")
 *             )
 *         )
 *     )
 * )
 */
class BillingPlansIndex {}

/**
 * @OA\Post(
 *     path="/v1/admin/billing/plans",
 *     summary="Cria um novo plano",
 *     tags={"Admin - Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/AdminBillingPlanPayload")
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Plano criado",
 *         @OA\JsonContent(ref="#/components/schemas/AdminBillingPlanResource")
 *     )
 * )
 */
class BillingPlansStore {}

/**
 * @OA\Get(
 *     path="/v1/admin/billing/plans/{plan}",
 *     summary="Exibe um plano",
 *     tags={"Admin - Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="plan", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(
 *         response=200,
 *         description="Plano",
 *         @OA\JsonContent(ref="#/components/schemas/AdminBillingPlanResource")
 *     )
 * )
 */
class BillingPlansShow {}

/**
 * @OA\Patch(
 *     path="/v1/admin/billing/plans/{plan}",
 *     summary="Atualiza um plano",
 *     tags={"Admin - Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="plan", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/AdminBillingPlanPayload")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Plano atualizado",
 *         @OA\JsonContent(ref="#/components/schemas/AdminBillingPlanResource")
 *     )
 * )
 */
class BillingPlansUpdate {}

/**
 * @OA\Get(
 *     path="/v1/admin/billing/companies/{company}/subscription",
 *     summary="Exibe assinatura de uma empresa",
 *     tags={"Admin - Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(
 *         response=200,
 *         description="Assinatura",
 *         @OA\JsonContent(ref="#/components/schemas/AdminBillingSubscriptionResource")
 *     )
 * )
 */
class BillingCompanySubscriptionShow {}

/**
 * @OA\Patch(
 *     path="/v1/admin/billing/companies/{company}/subscription",
 *     summary="Atualiza assinatura da empresa",
 *     tags={"Admin - Billing"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="company", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/AdminBillingSubscriptionPayload")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Assinatura atualizada",
 *         @OA\JsonContent(ref="#/components/schemas/AdminBillingSubscriptionResource")
 *     )
 * )
 */
class BillingCompanySubscriptionUpdate {}


/**
 * @OA\Schema(
 *     schema="AdminBillingPlanResource",
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
 *             schema="AdminBillingPlanFeatureValue",
 *             type="boolean"
 *         )
 *     ),
 *     @OA\Property(
 *         property="quotas",
 *         type="object",
 *         additionalProperties=@OA\Schema(
 *             schema="AdminBillingPlanQuotaValue",
 *             type="integer"
 *         )
 *     )
 * )
 */
class AdminBillingPlanResourceDoc {}


/**
 * @OA\Schema(
 *     schema="AdminBillingPlanPayload",
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
 *             schema="AdminBillingPlanPayloadFeatureValue",
 *             type="boolean"
 *         ),
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="quotas",
 *         type="object",
 *         additionalProperties=@OA\Schema(
 *             schema="AdminBillingPlanPayloadQuotaValue",
 *             type="integer"
 *         ),
 *         nullable=true
 *     )
 * )
 */
class AdminBillingPlanPayloadDoc {}


/**
 * @OA\Schema(
 *     schema="AdminBillingSubscriptionResource",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="company_id", type="string", format="uuid"),
 *     @OA\Property(property="plan", ref="#/components/schemas/AdminBillingPlanResource"),
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
 *         schema="AdminBillingSubscriptionMetadataValue",
 *         type="string"
 *     )))
 * )
 */
class AdminBillingSubscriptionResourceDoc {}


/**
 * @OA\Schema(
 *     schema="AdminBillingSubscriptionPayload",
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
 *         schema="AdminBillingSubscriptionPayloadMetadataValue",
 *         type="string"
 *     )))
 * )
 */
class AdminBillingSubscriptionPayloadDoc {}
