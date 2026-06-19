<?php

namespace App\Swagger\Admin\Blog;

/**
 * @OA\Delete(
 *     path="/v1/admin/blog/posts/{id}",
 *     summary="Remove um post do blog",
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
 *     @OA\Response(response=204, description="Removido"),
 *     @OA\Response(response=404, description="Post não encontrado")
 * )
 */
class Destroy {}
