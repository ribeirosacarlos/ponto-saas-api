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
 *     @OA\Response(response=200, description="Lista paginada")
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
 *     @OA\Response(response=201, description="Criado")
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
 *     @OA\Response(response=200, description="Aprovado")
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
 *     @OA\Response(response=200, description="Rejeitado")
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
 *     @OA\Response(response=200, description="Saldo atual")
 * )
 */
class VacationAdminBalance {}
