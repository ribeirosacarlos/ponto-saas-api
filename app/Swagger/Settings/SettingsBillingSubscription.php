<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsBillingSubscription",
 *     @OA\Property(property="status", type="string", nullable=true, example="active"),
 *     @OA\Property(property="status_label", type="string", example="Active"),
 *     @OA\Property(property="next_action", type="string", example="NONE"),
 *     @OA\Property(property="stripe_customer_id", type="string", nullable=true),
 *     @OA\Property(property="stripe_subscription_id", type="string", nullable=true),
 *     @OA\Property(property="subscription_status", type="string", nullable=true),
 *     @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="trial_days_remaining", type="integer", nullable=true),
 *     @OA\Property(property="current_period_end", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="billing_days_remaining", type="integer", nullable=true),
 *     @OA\Property(property="subscription_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="cancel_at_period_end", type="boolean", nullable=true),
 *     @OA\Property(property="canceled_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="access_expires_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="blocked_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="blocked_reason", type="string", nullable=true),
 *     @OA\Property(property="is_blocked", type="boolean"),
 *     @OA\Property(property="is_plan_active", type="boolean"),
 *     @OA\Property(property="can_cancel", type="boolean")
 * )
 */
class SettingsBillingSubscription {}
