<?php

namespace App\Swagger\Admin\Employees;

/**
 * @OA\Schema(
 *     schema="OvertimeBalanceResponse",
 *     type="object",
 *     @OA\Property(property="employee_id", type="string", format="uuid", example="c16ad58b-99c1-431d-bbd7-c8c71eca33e7"),
 *     @OA\Property(property="from", type="string", format="date", example="2026-01-01"),
 *     @OA\Property(property="to", type="string", format="date", example="2026-01-31"),
 *     @OA\Property(property="timezone", type="string", example="Europe/Madrid"),
 *     @OA\Property(property="totals", ref="#/components/schemas/OvertimeTotals"),
 *     @OA\Property(
 *         property="days",
 *         type="array",
 *         nullable=true,
 *         @OA\Items(ref="#/components/schemas/OvertimeDay")
 *     )
 * )
 */
class OvertimeResponseSchema {}
