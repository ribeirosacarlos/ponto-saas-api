<?php

namespace App\Swagger\Admin;

/**
 * @OA\Tag(
 *     name="Admin - Shifts",
 *     description="Gestão de jornadas de trabalho"
 * )
 */
class Shift {}


/**
 * LISTAR SHIFTS
 * ---------------------------------------------------------
 * @OA\Get(
 *     path="/v1/admin/shifts",
 *     summary="Lista todas as jornadas de trabalho",
 *     tags={"Admin - Shifts"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         required=false,
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=20),
 *             @OA\Property(property="total", type="integer", example=5),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     @OA\Property(property="id", type="integer", example=1),
 *                     @OA\Property(property="company_id", type="integer", example=3),
 *                     @OA\Property(property="name", type="string", example="Turno Normal"),
 *                     @OA\Property(property="start_time", type="string", example="08:00"),
 *                     @OA\Property(property="end_time", type="string", example="17:00"),
 *                     @OA\Property(property="is_flexible", type="boolean", example=false)
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class ShiftIndex {}

/**
 * CRIAR SHIFT
 * ---------------------------------------------------------
 * @OA\Post(
 *     path="/v1/admin/shifts",
 *     summary="Cria uma nova jornada de trabalho",
 *     tags={"Admin - Shifts"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name","start_time","end_time"},
 *             @OA\Property(property="name", type="string", example="Turno Manhã"),
 *             @OA\Property(property="start_time", type="string", example="06:00"),
 *             @OA\Property(property="end_time", type="string", example="14:00"),
 *             @OA\Property(property="is_flexible", type="boolean", example=false)
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Criado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=4),
 *             @OA\Property(property="company_id", type="integer", example=3),
 *             @OA\Property(property="name", type="string", example="Turno Manhã"),
 *             @OA\Property(property="start_time", type="string", example="06:00"),
 *             @OA\Property(property="end_time", type="string", example="14:00"),
 *             @OA\Property(property="is_flexible", type="boolean", example=false)
 *         )
 *     )
 * )
 */
class ShiftStore {}

/**
 * MOSTRAR SHIFT
 * ---------------------------------------------------------
 * @OA\Get(
 *     path="/v1/admin/shifts/{id}",
 *     summary="Exibe uma jornada específica",
 *     tags={"Admin - Shifts"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer", example=3)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Detalhes da jornada",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=3),
 *             @OA\Property(property="company_id", type="integer", example=3),
 *             @OA\Property(property="name", type="string", example="Turno Manhã"),
 *             @OA\Property(property="start_time", type="string", example="06:00"),
 *             @OA\Property(property="end_time", type="string", example="14:00"),
 *             @OA\Property(property="is_flexible", type="boolean", example=false)
 *         )
 *     ),
 *
 *     @OA\Response(response=404, description="Não encontrado")
 * )
 */
class ShiftShow {}

/**
 * ATUALIZAR SHIFT
 * ---------------------------------------------------------
 * @OA\Put(
 *     path="/v1/admin/shifts/{id}",
 *     summary="Atualiza uma jornada existente",
 *     tags={"Admin - Shifts"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer", example=3)
 *     ),
 *
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="Turno Atualizado"),
 *             @OA\Property(property="start_time", type="string", example="07:00"),
 *             @OA\Property(property="end_time", type="string", example="15:00"),
 *             @OA\Property(property="is_flexible", type="boolean", example=true)
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Atualizado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer", example=3),
 *             @OA\Property(property="name", type="string", example="Turno Atualizado")
 *         )
 *     )
 * )
 */
class ShiftUpdate {}

/**
 * DELETAR SHIFT
 * ---------------------------------------------------------
 * @OA\Delete(
 *     path="/v1/admin/shifts/{id}",
 *     summary="Remove uma jornada de trabalho",
 *     tags={"Admin - Shifts"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="integer", example=3)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Jornada removida",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Deletado")
 *         )
 *     )
 * )
 */
class ShiftDestroy {}
