<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsSecurity",
 *     @OA\Property(property="two_factor_enabled", type="boolean", nullable=true),
 *     @OA\Property(property="last_login_at", type="string", format="date-time", nullable=true)
 * )
 */
class SettingsSecurity {}
