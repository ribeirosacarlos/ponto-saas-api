<?php

namespace App\Swagger\Billing;

/**
 * @OA\Schema(
 *     schema="PublicBillingCheckoutSessionRequest",
 *     required={"company_id","plan_id"},
 *     @OA\Property(property="company_id", type="string", format="uuid", example="00000000-0000-0000-0000-000000000000"),
 *     @OA\Property(property="plan_id", type="string", format="uuid", example="00000000-0000-0000-0000-000000000000"),
 *     @OA\Property(property="stripe_price_id", type="string", nullable=true, example="price_xyz123"),
 *     @OA\Property(property="honeypot", type="string", nullable=true, description="Campo oculto utilizado para detectar spam")
 * )
 */
class PublicBillingCheckoutSessionRequestSchema {}
