<?php

namespace App\Swagger\Admin\Blog;

/**
 * @OA\Patch(
 *     path="/v1/admin/blog/posts/{id}/unpublish",
 *     summary="Despublica um post do blog",
 *     description="Define status=draft.",
 *     tags={"Admin - Blog"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Despublicado",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="data", ref="#/components/schemas/BlogPostAdminResource")
 *         )
 *     ),
 *
 *     @OA\Response(response=404, description="Post não encontrado")
 * )
 */
class Unpublish {}
