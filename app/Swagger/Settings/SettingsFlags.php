<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsFlags",
 *     @OA\Property(property="can_access_system", type="boolean"),
 *     @OA\Property(property="is_trial", type="boolean"),
 *     @OA\Property(property="is_trial_active", type="boolean"),
 *     @OA\Property(property="is_subscription_active", type="boolean"),
 *     @OA\Property(property="requires_action", type="boolean")
 * )
 */
class SettingsFlags {}
