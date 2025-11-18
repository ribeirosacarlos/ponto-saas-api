<?php

namespace App\Swagger\Employee;

/**
 * @OA\Tag(
 *     name="Employee - Adjustments",
 *     description="Solicitações de ajuste de ponto feitas pelo funcionário"
 * )
 */
class Adjustment {}


/**
 * ==========================================
 * Solicitar ajuste de ponto
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/employee/adjustments",
 *     summary="Solicita um ajuste de ponto",
 *     description="O funcionário envia uma solicitação de correção de horário (entrada/saída). A solicitação entra como 'pending'.",
 *     tags={"Employee - Adjustments"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         description="Dados do ajuste solicitado",
 *         @OA\JsonContent(
 *             required={"original_time","corrected_time","reason"},
 *
 *             @OA\Property(
 *                 property="original_time",
 *                 type="string",
 *                 format="date-time",
 *                 example="2025-02-10 08:00:00"
 *             ),
 *
 *             @OA\Property(
 *                 property="corrected_time",
 *                 type="string",
 *                 format="date-time",
 *                 example="2025-02-10 08:10:00"
 *             ),
 *
 *             @OA\Property(
 *                 property="reason",
 *                 type="string",
 *                 example="Esqueci de registrar a entrada"
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Ajuste criado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="user_id", type="integer", example=10),
 *             @OA\Property(property="original_time", type="string", example="2025-02-10 08:00:00"),
 *             @OA\Property(property="corrected_time", type="string", example="2025-02-10 08:10:00"),
 *             @OA\Property(property="reason", type="string", example="Esqueci de registrar a entrada"),
 *             @OA\Property(property="status", type="string", example="pending")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=403,
 *         description="Usuário não autorizado a solicitar ajustes"
 *     )
 * )
 */
class AdjustmentRequest {}
