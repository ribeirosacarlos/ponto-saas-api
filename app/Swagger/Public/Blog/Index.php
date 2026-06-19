<?php

namespace App\Swagger\Public\Blog;

/**
 * @OA\Get(
 *     path="/v1/public/blog/posts",
 *     summary="Lista posts publicados do blog",
 *     description="Endpoint público, sem autenticação. Retorna apenas posts com status=published.",
 *     tags={"Public - Blog"},
 *
 *     @OA\Parameter(
 *         name="language",
 *         in="query",
 *         required=false,
 *         description="Idioma de exibição (default: pt)",
 *
 *         @OA\Schema(type="string", enum={"pt","es","en"})
 *     ),
 *
 *     @OA\Parameter(
 *         name="category",
 *         in="query",
 *         required=false,
 *
 *         @OA\Schema(type="string", example="compliance")
 *     ),
 *
 *     @OA\Parameter(
 *         name="featured",
 *         in="query",
 *         required=false,
 *
 *         @OA\Schema(type="boolean")
 *     ),
 *
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         required=false,
 *
 *         @OA\Schema(type="string", maxLength=100)
 *     ),
 *
 *     @OA\Parameter(
 *         name="sort",
 *         in="query",
 *         required=false,
 *         description="Critério de ordenação (default: published_at_desc)",
 *
 *         @OA\Schema(type="string", enum={"published_at_desc","trending_score_desc"})
 *     ),
 *
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         required=false,
 *
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         required=false,
 *         description="Máximo 50",
 *
 *         @OA\Schema(type="integer", example=10)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada de posts publicados",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *
 *                 @OA\Items(ref="#/components/schemas/BlogPostListItem")
 *             ),
 *
 *             @OA\Property(
 *                 property="meta",
 *                 type="object",
 *                 @OA\Property(property="total", type="integer", example=42),
 *                 @OA\Property(property="page", type="integer", example=1),
 *                 @OA\Property(property="per_page", type="integer", example=10),
 *                 @OA\Property(property="last_page", type="integer", example=5)
 *             )
 *         )
 *     )
 * )
 */
class Index {}
