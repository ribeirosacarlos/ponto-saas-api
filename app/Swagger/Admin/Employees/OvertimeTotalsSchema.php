<?php

namespace App\Swagger\Admin\Employees;

/**
 * @OA\Schema(
 *     schema="OvertimeTotals",
 *     type="object",
 *     @OA\Property(property="worked_minutes", type="integer", example=10230),
 *     @OA\Property(property="expected_minutes", type="integer", example=9600),
 *     @OA\Property(property="balance_minutes", type="integer", example=630),
 *     @OA\Property(property="extra_minutes", type="integer", example=780),
 *     @OA\Property(property="debt_minutes", type="integer", example=-150),
 *     @OA\Property(property="abs_debt_minutes", type="integer", example=150),
 *     @OA\Property(property="worked_hhmm", type="string", example="170:30"),
 *     @OA\Property(property="expected_hhmm", type="string", example="160:00"),
 *     @OA\Property(property="balance_hhmm", type="string", example="+10:30"),
 *     @OA\Property(property="extra_hhmm", type="string", example="13:00"),
 *     @OA\Property(property="debt_hhmm", type="string", example="02:30")
 * )
 */
class OvertimeTotalsSchema {}
