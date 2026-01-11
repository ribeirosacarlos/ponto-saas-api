<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsBillingPlan",
 *     @OA\Property(property="name", type="string", nullable=true),
 *     @OA\Property(property="slug", type="string", nullable=true),
 *     @OA\Property(property="price_cents", type="integer", nullable=true),
 *     @OA\Property(property="currency", type="string", nullable=true),
 *     @OA\Property(property="billing_interval", type="string", enum={"month","year","one_time"}, nullable=true),
 *     @OA\Property(
 *         property="limits",
 *         type="object",
 *         nullable=true,
 *         additionalProperties=@OA\AdditionalProperties(type="integer")
 *     )
 * )
 */
class SettingsBillingPlan {}
