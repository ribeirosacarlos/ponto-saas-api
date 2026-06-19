<?php

namespace App\Swagger\Admin\Blog;

/**
 * @OA\Patch(
 *     path="/v1/admin/blog/posts/{id}/publish",
 *     summary="Publica um post do blog",
 *     description="Define status=published e, se ainda não houver, define published_at como agora.",
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
 *         description="Publicado",
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
class Publish {}
