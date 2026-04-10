<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsWorkday",
 *     @OA\Property(property="default_shift", ref="#/components/schemas/SettingsWorkdayDefaultShift", nullable=true),
 *     @OA\Property(property="tolerance_minutes", type="integer"),
 *     @OA\Property(property="rounding_minutes", type="integer", nullable=true),
 *     @OA\Property(property="geolocation_enabled", type="boolean"),
 *     @OA\Property(property="geolocation_required", type="boolean"),
 *     @OA\Property(property="require_photo", type="boolean", nullable=true)
 * )
 */
class SettingsWorkday {}
