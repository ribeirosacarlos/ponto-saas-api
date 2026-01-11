<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsOverviewResponse",
 *     @OA\Property(property="billing", ref="#/components/schemas/SettingsBilling"),
 *     @OA\Property(property="flags", ref="#/components/schemas/SettingsFlags"),
 *     @OA\Property(property="links", ref="#/components/schemas/SettingsLinks"),
 *     @OA\Property(property="company", ref="#/components/schemas/SettingsCompany"),
 *     @OA\Property(property="workday", ref="#/components/schemas/SettingsWorkday"),
 *     @OA\Property(property="security", ref="#/components/schemas/SettingsSecurity"),
 *     @OA\Property(property="usage", ref="#/components/schemas/SettingsUsage"),
 *     @OA\Property(property="compliance", ref="#/components/schemas/SettingsCompliance")
 * )
 */
class SettingsOverviewResponse {}
