<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsUsageEmployees",
 *     @OA\Property(property="current", type="integer"),
 *     @OA\Property(property="limit", type="integer", nullable=true),
 *     @OA\Property(property="over_limit", type="boolean")
 * )
 */
class SettingsUsageEmployees {}
