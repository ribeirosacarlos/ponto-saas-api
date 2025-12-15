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
 *         description="Lista paginada"
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
 *     @OA\Response(response=201, description="Solicitação criada")
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
 *             @OA\Property(property="accrued", type="number", format="float", example=15.0),
 *             @OA\Property(property="used", type="number", example=5.0),
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
 *     @OA\Response(response=200, description="Cancelado")
 * )
 */
class VacationCancel {}
