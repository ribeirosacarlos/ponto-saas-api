<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsWorkdayShiftDay",
 *     @OA\Property(property="weekday", type="integer", minimum=1, maximum=7),
 *     @OA\Property(property="is_working_day", type="boolean"),
 *     @OA\Property(property="start_time", type="string", nullable=true),
 *     @OA\Property(property="end_time", type="string", nullable=true),
 *     @OA\Property(property="break_expected", type="boolean")
 * )
 */
class SettingsWorkdayShiftDay {}
