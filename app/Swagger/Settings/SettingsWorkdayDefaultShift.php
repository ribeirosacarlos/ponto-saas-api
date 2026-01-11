<?php

namespace App\Swagger\Settings;

/**
 * @OA\Schema(
 *     schema="SettingsWorkdayDefaultShift",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="start_time", type="string", example="09:00"),
 *     @OA\Property(property="end_time", type="string", example="18:00"),
 *     @OA\Property(
 *         property="days",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/SettingsWorkdayShiftDay")
 *     )
 * )
 */
class SettingsWorkdayDefaultShift {}
