<?php

namespace App\Swagger\Admin\Blog;

/**
 * @OA\Get(
 *     path="/v1/admin/blog/posts/{id}",
 *     summary="Exibe um post do blog",
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
 *         description="OK",
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
class Show {}
