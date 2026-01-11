<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsCompany",
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="timezone", type="string", nullable=true),
 *     @OA\Property(property="country", type="string", nullable=true),
 *     @OA\Property(property="locale", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 */
class SettingsCompany {}
