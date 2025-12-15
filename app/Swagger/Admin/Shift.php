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
 *                     @OA\Property(property="id", type="string", format="uuid"),
 *                     @OA\Property(property="company_id", type="string", format="uuid"),
 *                     @OA\Property(property="name", type="string", example="Jornada Padrão (Seg–Sex)"),
 *                     @OA\Property(property="start_time", type="string", example="09:00"),
 *                     @OA\Property(property="end_time", type="string", example="18:00"),
 *                     @OA\Property(property="is_flexible", type="boolean", example=false),
 *                     @OA\Property(property="is_default", type="boolean", example=true),
 *                     @OA\Property(
 *                         property="shift_days",
 *                         type="array",
 *                         @OA\Items(
 *                             @OA\Property(property="weekday", type="integer", example=1),
 *                             @OA\Property(property="is_working_day", type="boolean", example=true),
 *                             @OA\Property(property="start_time", type="string", example="09:00", nullable=true),
 *                             @OA\Property(property="end_time", type="string", example="18:00", nullable=true),
 *                             @OA\Property(property="break_start_time", type="string", example="13:00", nullable=true),
 *                             @OA\Property(property="break_end_time", type="string", example="14:00", nullable=true),
 *                             @OA\Property(property="break_minutes", type="integer", example=60, nullable=true)
 *                         )
 *                     )
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
 *             required={"name","days"},
 *             @OA\Property(property="name", type="string", example="Jornada Padrão (Seg–Sex)"),
 *             @OA\Property(property="is_flexible", type="boolean", example=false),
 *             @OA\Property(property="is_default", type="boolean", example=true),
 *             @OA\Property(
 *                 property="days",
 *                 type="array",
 *                 minItems=7,
 *                 maxItems=7,
 *                 @OA\Items(
 *                     required={"weekday","is_working_day"},
 *                     @OA\Property(property="weekday", type="integer", minimum=1, maximum=7, example=1),
 *                     @OA\Property(property="is_working_day", type="boolean", example=true),
 *                     @OA\Property(property="start_time", type="string", example="09:00", nullable=true),
 *                     @OA\Property(property="end_time", type="string", example="18:00", nullable=true),
 *                     @OA\Property(property="break_start_time", type="string", example="13:00", nullable=true),
 *                     @OA\Property(property="break_end_time", type="string", example="14:00", nullable=true),
 *                     @OA\Property(property="break_minutes", type="integer", example=60, nullable=true)
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Criado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", format="uuid"),
 *             @OA\Property(property="company_id", type="string", format="uuid"),
 *             @OA\Property(property="name", type="string", example="Jornada Padrão (Seg–Sex)"),
 *             @OA\Property(property="start_time", type="string", example="09:00"),
 *             @OA\Property(property="end_time", type="string", example="18:00"),
 *             @OA\Property(property="is_flexible", type="boolean", example=false),
 *             @OA\Property(property="is_default", type="boolean", example=true),
 *             @OA\Property(property="shift_days", type="array",
 *                 @OA\Items(ref="#/components/schemas/ShiftDay"))
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
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Detalhes da jornada",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", format="uuid"),
 *             @OA\Property(property="company_id", type="string", format="uuid"),
 *             @OA\Property(property="name", type="string", example="Jornada Padrão (Seg–Sex)"),
 *             @OA\Property(property="start_time", type="string", example="09:00"),
 *             @OA\Property(property="end_time", type="string", example="18:00"),
 *             @OA\Property(property="is_flexible", type="boolean", example=false),
 *             @OA\Property(property="is_default", type="boolean", example=true),
 *             @OA\Property(property="shift_days", type="array",
 *                 @OA\Items(ref="#/components/schemas/ShiftDay"))
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
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="Jornada Flex"),
 *             @OA\Property(property="is_flexible", type="boolean", example=true),
 *             @OA\Property(property="is_default", type="boolean", example=false),
 *             @OA\Property(
 *                 property="days",
 *                 type="array",
 *                 minItems=7,
 *                 maxItems=7,
 *                 @OA\Items(ref="#/components/schemas/ShiftDayPayload")
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Atualizado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", format="uuid"),
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
 *         @OA\Schema(type="string", format="uuid")
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

/**
 * @OA\Schema(
 *     schema="ShiftDay",
 *     @OA\Property(property="weekday", type="integer", example=1),
 *     @OA\Property(property="is_working_day", type="boolean", example=true),
 *     @OA\Property(property="start_time", type="string", nullable=true, example="09:00"),
 *     @OA\Property(property="end_time", type="string", nullable=true, example="18:00"),
 *     @OA\Property(property="break_start_time", type="string", nullable=true, example="13:00"),
 *     @OA\Property(property="break_end_time", type="string", nullable=true, example="14:00"),
 *     @OA\Property(property="break_minutes", type="integer", nullable=true, example=60)
 * )
 */
class ShiftDaySchema {}

/**
 * @OA\Schema(
 *     schema="ShiftDayPayload",
 *     required={"weekday","is_working_day"},
 *     @OA\Property(property="weekday", type="integer", minimum=1, maximum=7, example=1),
 *     @OA\Property(property="is_working_day", type="boolean", example=true),
 *     @OA\Property(property="start_time", type="string", nullable=true, example="09:00"),
 *     @OA\Property(property="end_time", type="string", nullable=true, example="18:00"),
 *     @OA\Property(property="break_start_time", type="string", nullable=true, example="13:00"),
 *     @OA\Property(property="break_end_time", type="string", nullable=true, example="14:00"),
 *     @OA\Property(property="break_minutes", type="integer", nullable=true, example=60)
 * )
 */
class ShiftDayPayloadSchema {}
