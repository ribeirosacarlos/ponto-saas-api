<?php

namespace App\Swagger\Admin;

/**
 * @OA\Tag(
 *     name="Admin - Holidays",
 *     description="Gestão de feriados corporativos"
 * )
 */
class Holiday {}

/**
 * @OA\Get(
 *     path="/v1/admin/holidays",
 *     summary="Lista feriados da empresa",
 *     tags={"Admin - Holidays"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="start", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="end", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="scope", in="query", @OA\Schema(type="string", enum={"national","regional","local","company"})),
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=50),
 *             @OA\Property(property="total", type="integer", example=9),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/HolidayResource")
 *             )
 *         )
 *     )
 * )
 */
class HolidayIndex {}

/**
 * @OA\Post(
 *     path="/v1/admin/holidays",
 *     summary="Cria um feriado",
 *     tags={"Admin - Holidays"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         @OA\JsonContent(ref="#/components/schemas/HolidayPayload")
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Criado",
 *         @OA\JsonContent(ref="#/components/schemas/HolidayResource")
 *     )
 * )
 */
class HolidayStore {}

/**
 * @OA\Put(
 *     path="/v1/admin/holidays/{id}",
 *     summary="Atualiza um feriado",
 *     tags={"Admin - Holidays"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         @OA\JsonContent(ref="#/components/schemas/HolidayPayload")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Atualizado",
 *         @OA\JsonContent(ref="#/components/schemas/HolidayResource")
 *     )
 * )
 */
class HolidayUpdate {}

/**
 * @OA\Delete(
 *     path="/v1/admin/holidays/{id}",
 *     summary="Remove um feriado",
 *     tags={"Admin - Holidays"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(
 *         response=200,
 *         description="Removido",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Feriado removido.")
 *         )
 *     )
 * )
 */
class HolidayDestroy {}

/**
 * @OA\Schema(
 *     schema="HolidayResource",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="company_id", type="string", format="uuid"),
 *     @OA\Property(property="date", type="string", format="date", example="2025-12-25"),
 *     @OA\Property(property="name", type="string", example="Navidad"),
 *     @OA\Property(property="scope", type="string", example="national")
 * )
 */
class HolidayResourceSchema {}

/**
 * @OA\Schema(
 *     schema="HolidayPayload",
 *     required={"date","name"},
 *     @OA\Property(property="date", type="string", format="date", example="2025-08-15"),
 *     @OA\Property(property="name", type="string", example="Asunción de la Virgen"),
 *     @OA\Property(property="scope", type="string", enum={"national","regional","local","company"}, example="national")
 * )
 */
class HolidayPayloadSchema {}
