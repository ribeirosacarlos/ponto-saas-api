<?php

namespace App\Swagger\Billing;

/**
 * @OA\Schema(
 *     schema="BillingPlanResource",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="price_cents", type="integer", example=2900),
 *     @OA\Property(property="price", type="number", format="float", example=29.00),
 *     @OA\Property(property="currency", type="string", example="EUR"),
 *     @OA\Property(property="billing_interval", type="string", enum={"month","year","one_time"}, nullable=true),
 *     @OA\Property(property="interval", type="string", enum={"month","year","one_time"}, nullable=true),
 *     @OA\Property(property="trial_days", type="integer", example=14),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="sort_order", type="integer"),
 *     @OA\Property(property="stripe_price_id", type="string", nullable=true),
     *     @OA\Property(
     *         property="features",
     *         type="object",
     *         nullable=true,
     *         additionalProperties=@OA\Schema(schema="BillingPlanFeatureValue", type="boolean")
     *     ),
     *     @OA\Property(
     *         property="quotas",
     *         type="object",
     *         nullable=true,
     *         additionalProperties=@OA\Schema(schema="BillingPlanQuotaValue", type="integer")
     *     )
 * )
 */
class BillingPlanResourceSchema {}
