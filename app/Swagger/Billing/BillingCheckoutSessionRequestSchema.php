<?php

namespace App\Swagger\Billing;

/**
 * @OA\Schema(
 *     schema="BillingCheckoutSessionRequest",
 *     required={"plan_id"},
 *     @OA\Property(property="plan_id", type="string", format="uuid", example="00000000-0000-0000-0000-000000000000")
 * )
 */
class BillingCheckoutSessionRequestSchema {}
