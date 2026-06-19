<?php

namespace App\Swagger\Admin\Blog;

/**
 * @OA\Get(
 *     path="/v1/admin/blog/posts",
 *     summary="Lista posts do blog",
 *     description="Retorna uma lista paginada de posts, com filtro opcional por status e categoria.",
 *     tags={"Admin - Blog"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         required=false,
 *         description="Filtra por status do post",
 *
 *         @OA\Schema(type="string", enum={"draft","published","archived"})
 *     ),
 *
 *     @OA\Parameter(
 *         name="category",
 *         in="query",
 *         required=false,
 *         description="Filtra por categoria",
 *
 *         @OA\Schema(type="string", example="compliance")
 *     ),
 *
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         required=false,
 *         description="Itens por página",
 *
 *         @OA\Schema(type="integer", example=20)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada",
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
 *                 @OA\Property(property="per_page", type="integer", example=20)
 *             )
 *         )
 *     )
 * )
 */
class Index {}

/**
 * @OA\Schema(
 *     schema="BlogPostListItem",
 *
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="slug", type="string", example="ponto-eletronico-espanha"),
 *     @OA\Property(property="title", type="string", example="Como funciona o controle de ponto na Espanha"),
 *     @OA\Property(property="excerpt", type="string", example="Entenda as regras de jornada e fiscalização."),
 *     @OA\Property(property="cover_url", type="string", nullable=true),
 *     @OA\Property(property="category", type="string", example="compliance"),
 *     @OA\Property(property="author", type="string", example="Equipe Jornafy"),
 *     @OA\Property(property="reading_time", type="string", nullable=true, example="5 min"),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="featured", type="boolean"),
 *     @OA\Property(property="trending_score", type="integer", nullable=true, example=80),
 *     @OA\Property(property="language", type="string", nullable=true, example="pt")
 * )
 */
class BlogPostListItemSchema {}
