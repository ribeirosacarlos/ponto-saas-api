<?php

namespace App\Swagger\Admin;

/**
 * @OA\Tag(
 *     name="Admin - Vacations",
 *     description="Gestão de solicitações de férias"
 * )
 */
class Vacation {}

/**
 * @OA\Get(
 *     path="/v1/admin/vacations",
 *     summary="Lista solicitações de férias da empresa",
 *     tags={"Admin - Vacations"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="user_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="data", type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="string", format="uuid"),
 *                     @OA\Property(property="user_id", type="string", format="uuid"),
 *                     @OA\Property(property="start_date", type="string", format="date"),
 *                     @OA\Property(property="status", type="string", example="approved")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class VacationAdminIndex {}

/**
 * @OA\Post(
 *     path="/v1/admin/vacations",
 *     summary="Cria férias para um colaborador",
 *     tags={"Admin - Vacations"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"user_id","start_date","end_date"},
 *             @OA\Property(property="user_id", type="string", format="uuid"),
 *             @OA\Property(property="start_date", type="string", format="date"),
 *             @OA\Property(property="end_date", type="string", format="date"),
 *             @OA\Property(property="status", type="string", enum={"pending","approved"})
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Criado",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", format="uuid"),
 *             @OA\Property(property="status", type="string", example="pending")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Saldo insuficiente ou conflito",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="The given data was invalid."),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="dates", type="array",
 *                     @OA\Items(type="string", example="Já existe um pedido de férias que conflita com este período.")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class VacationAdminStore {}

/**
 * @OA\Post(
 *     path="/v1/admin/vacations/{id}/approve",
 *     summary="Aprova solicitação de férias",
 *     tags={"Admin - Vacations"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(
 *         response=200,
 *         description="Aprovado",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="approved"),
 *             @OA\Property(property="approved_at", type="string", format="date-time")
 *         )
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Saldo insuficiente",
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
class VacationApprove {}

/**
 * @OA\Post(
 *     path="/v1/admin/vacations/{id}/reject",
 *     summary="Rejeita solicitação",
 *     tags={"Admin - Vacations"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"rejection_reason"},
 *             @OA\Property(property="rejection_reason", type="string")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Rejeitado",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="rejected"),
 *             @OA\Property(property="rejection_reason", type="string", example="Período crítico de operação.")
 *         )
 *     )
 * )
 */
class VacationReject {}

/**
 * @OA\Get(
 *     path="/v1/admin/vacations/balance/{user_id}",
 *     summary="Consulta saldo de férias do colaborador",
 *     tags={"Admin - Vacations"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="user_id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(
 *         response=200,
 *         description="Saldo atual",
 *         @OA\JsonContent(
 *             @OA\Property(property="policy", type="object",
 *                 @OA\Property(property="name", type="string", example="Política Padrão Espanha")
 *             ),
 *             @OA\Property(property="accrued", type="number", example=12.5),
 *             @OA\Property(property="used", type="number", example=5.0),
 *             @OA\Property(property="adjustment", type="number", example=0.5),
 *             @OA\Property(property="available", type="number", example=8.0)
 *         )
 *     ),
 *     @OA\Response(response=404, description="Funcionário não encontrado")
 * )
 */
class VacationAdminBalance {}
