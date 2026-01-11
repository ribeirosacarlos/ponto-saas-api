<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsBilling",
 *     @OA\Property(property="plan", ref="#/components/schemas/SettingsBillingPlan"),
 *     @OA\Property(property="subscription", ref="#/components/schemas/SettingsBillingSubscription")
 * )
 */
class SettingsBilling {}
