<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsCompliance",
 *     @OA\Property(property="log_retention_days", type="integer", nullable=true),
 *     @OA\Property(property="export_enabled", type="boolean")
 * )
 */
class SettingsCompliance {}
