<?php

namespace App\Swagger\Commercial;

/**
 * @OA\Tag(
 *     name="Commercial - Steps",
 *     description="Etapas do funil comercial. Leitura disponível para os 3 papéis; gestão (criar/editar/remover/reordenar) restrita a super_admin e commercial_manager."
 * )
 */
class Steps {}

/**
 * @OA\Get(
 *     path="/v1/admin/commercial/steps",
 *     summary="Lista etapas comerciais ativas e inativas",
 *     tags={"Commercial - Steps"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200, description="Lista de etapas ordenadas por position")
 * )
 */
class StepsIndex {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/steps",
 *     summary="Cria uma etapa comercial",
 *     description="Restrito a super_admin e commercial_manager.",
 *     tags={"Commercial - Steps"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name","position"},
 *
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="description", type="string", nullable=true),
 *         @OA\Property(property="position", type="integer"),
 *         @OA\Property(property="default_due_days", type="integer", nullable=true),
 *         @OA\Property(property="active", type="boolean", default=true)
 *     )),
 *
 *     @OA\Response(response=201, description="Etapa criada"),
 *     @OA\Response(response=403, description="commercial_agent não pode gerenciar etapas")
 * )
 */
class StepsStore {}

/**
 * @OA\Put(
 *     path="/v1/admin/commercial/steps/{id}",
 *     summary="Atualiza uma etapa comercial",
 *     tags={"Commercial - Steps"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Etapa atualizada")
 * )
 */
class StepsUpdate {}

/**
 * @OA\Delete(
 *     path="/v1/admin/commercial/steps/{id}",
 *     summary="Remove uma etapa comercial",
 *     description="Bloqueado pelo banco se houver lead_step_logs referenciando a etapa.",
 *     tags={"Commercial - Steps"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Etapa removida")
 * )
 */
class StepsDestroy {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/steps/reorder",
 *     summary="Reordena as etapas comerciais",
 *     tags={"Commercial - Steps"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"steps"},
 *
 *         @OA\Property(property="steps", type="array", @OA\Items(
 *             @OA\Property(property="id", type="string", format="uuid"),
 *             @OA\Property(property="position", type="integer")
 *         ))
 *     )),
 *
 *     @OA\Response(response=200, description="Etapas reordenadas")
 * )
 */
class StepsReorder {}
