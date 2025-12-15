<?php

namespace App\Swagger\Employee;

/**
 * @OA\Tag(
 *     name="Employee - Vacations",
 *     description="Solicitação e acompanhamento de férias do colaborador"
 * )
 */
class Vacation {}

/**
 * @OA\Get(
 *     path="/v1/employee/vacations",
 *     summary="Lista meus pedidos de férias",
 *     tags={"Employee - Vacations"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="data", type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="string", format="uuid"),
 *                     @OA\Property(property="start_date", type="string", format="date", example="2025-07-01"),
 *                     @OA\Property(property="end_date", type="string", format="date", example="2025-07-10"),
 *                     @OA\Property(property="status", type="string", example="pending"),
 *                     @OA\Property(property="requested_days", type="number", example=7.00)
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class VacationIndex {}

/**
 * @OA\Post(
 *     path="/v1/employee/vacations",
 *     summary="Solicita novas férias",
 *     tags={"Employee - Vacations"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"start_date","end_date"},
 *             @OA\Property(property="start_date", type="string", format="date", example="2025-07-01"),
 *             @OA\Property(property="end_date", type="string", format="date", example="2025-07-10"),
 *             @OA\Property(property="notes", type="string", nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Solicitação criada",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", format="uuid"),
 *             @OA\Property(property="status", type="string", example="pending"),
 *             @OA\Property(property="requested_days", type="number", example=8.00)
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Saldo insuficiente ou conflito",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="The given data was invalid."),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="requested_days", type="array",
 *                     @OA\Items(type="string", example="Você não possui saldo suficiente para este período.")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class VacationStore {}

/**
 * @OA\Get(
 *     path="/v1/employee/vacations/balance",
 *     summary="Consulta saldo de férias",
 *     tags={"Employee - Vacations"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Saldo corrente",
 *         @OA\JsonContent(
 *             @OA\Property(property="policy", type="object",
 *                 @OA\Property(property="name", type="string", example="Política Padrão Espanha"),
 *                 @OA\Property(property="counting_method", type="string", example="calendar_days")
 *             ),
 *             @OA\Property(property="accrued", type="number", format="float", example=15.0),
 *             @OA\Property(property="used", type="number", example=5.0),
 *             @OA\Property(property="adjustment", type="number", example=0.0),
 *             @OA\Property(property="available", type="number", example=10.0)
 *         )
 *     )
 * )
 */
class VacationBalance {}

/**
 * @OA\Delete(
 *     path="/v1/employee/vacations/{id}",
 *     summary="Cancela solicitação pendente",
 *     tags={"Employee - Vacations"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Response(response=200, description="Cancelado"),
 *     @OA\Response(
 *         response=422,
 *         description="Não é possível cancelar",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="The given data was invalid."),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="status", type="array",
 *                     @OA\Items(type="string", example="Somente pedidos pendentes podem ser cancelados.")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class VacationCancel {}
