<?php

namespace App\Swagger\Employee;

/**
 * @OA\Tag(
 *     name="Employee - Adjustments",
 *     description="Ajustes de ponto solicitados pelo funcionario"
 * )
 */
class Adjustment {}

/**
 * ==========================================
 * Solicitar ajuste de ponto
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/employee/time-entries/{timeEntry}/adjustment",
 *     summary="Solicita ajuste para um registro existente",
 *     description="Solicita ajuste sobre um time entry existente. O registro oficial so muda apos aprovacao.",
 *     tags={"Employee - Adjustments"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="timeEntry",
 *         in="path",
 *         required=true,
 *         description="UUID do time entry a ser ajustado",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\RequestBody(
 *         required=true,
 *         description="Campos propostos para o ajuste",
 *         @OA\JsonContent(
 *             required={"reason"},
 *             @OA\Property(
 *                 property="proposed_clocked_at",
 *                 type="string",
 *                 format="date-time",
 *                 example="2026-02-10 08:05:00"
 *             ),
 *             @OA\Property(
 *                 property="proposed_type",
 *                 type="string",
 *                 enum={"in", "out"},
 *                 example="in"
 *             ),
 *             @OA\Property(
 *                 property="proposed_latitude",
 *                 type="number",
 *                 format="double",
 *                 example=-23.56667
 *             ),
 *             @OA\Property(
 *                 property="proposed_longitude",
 *                 type="number",
 *                 format="double",
 *                 example=-46.65
 *             ),
 *             @OA\Property(
 *                 property="proposed_source",
 *                 type="string",
 *                 example="mobile"
 *             ),
 *             @OA\Property(
 *                 property="reason",
 *                 type="string",
 *                 example="Corrigir entrada pois esqueci de bater"
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Ajuste registrado como pending",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", format="uuid"),
 *             @OA\Property(property="company_id", type="string", format="uuid"),
 *             @OA\Property(property="user_id", type="string", format="uuid"),
 *             @OA\Property(property="user_shift_id", type="string", format="uuid", nullable=true),
 *             @OA\Property(property="clocked_at", type="string", format="date-time"),
 *             @OA\Property(property="type", type="string", enum={"in", "out"}),
 *             @OA\Property(property="event_kind", type="string", nullable=true),
 *             @OA\Property(property="source", type="string"),
 *             @OA\Property(property="adjustment_status", type="string", example="pending"),
 *             @OA\Property(property="adjustment_reason", type="string"),
 *             @OA\Property(property="proposed_clocked_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="proposed_type", type="string", nullable=true, enum={"in", "out"}),
 *             @OA\Property(property="proposed_latitude", type="number", nullable=true),
 *             @OA\Property(property="proposed_longitude", type="number", nullable=true),
 *             @OA\Property(property="proposed_source", type="string", nullable=true),
 *             @OA\Property(property="adjustment_requested_at", type="string", format="date-time")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=403,
 *         description="Usuario nao autorizado"
 *     )
 * )
 */
class AdjustmentRequest {}
