<?php

namespace App\Swagger\Admin\Employees;

/**
 * @OA\Schema(
 *     schema="OvertimeDay",
 *     type="object",
 *     @OA\Property(property="date", type="string", format="date", example="2026-01-05"),
 *     @OA\Property(property="worked_minutes", type="integer", example=480),
 *     @OA\Property(property="expected_minutes", type="integer", example=480),
 *     @OA\Property(property="balance_minutes", type="integer", example=0),
 *     @OA\Property(property="worked_hhmm", type="string", example="8:00"),
 *     @OA\Property(property="expected_hhmm", type="string", example="8:00"),
 *     @OA\Property(property="balance_hhmm", type="string", example="00:00"),
 *     @OA\Property(property="status", type="string", enum={"extra","debt","even"}, example="even"),
 *     @OA\Property(property="ignored", type="boolean", example=false),
 *     @OA\Property(property="reason", type="string", example="too_few_entries", nullable=true)
 * )
 */
class OvertimeDaySchema {}
