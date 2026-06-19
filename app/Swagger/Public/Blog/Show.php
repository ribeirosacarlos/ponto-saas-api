<?php

namespace App\Swagger\Public\Blog;

/**
 * @OA\Get(
 *     path="/v1/public/blog/posts/{slug}",
 *     summary="Exibe um post publicado pelo slug",
 *     description="Endpoint público, sem autenticação. Retorna 404 se o post não existir ou não estiver publicado.",
 *     tags={"Public - Blog"},
 *
 *     @OA\Parameter(
 *         name="slug",
 *         in="path",
 *         required=true,
 *
 *         @OA\Schema(type="string", example="ponto-eletronico-espanha")
 *     ),
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
 *     @OA\Response(
 *         response=200,
 *         description="OK",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="data", ref="#/components/schemas/BlogPostDetailResource")
 *         )
 *     ),
 *
 *     @OA\Response(response=404, description="Post não encontrado ou não publicado")
 * )
 */
class Show {}

/**
 * @OA\Schema(
 *     schema="BlogPostDetailResource",
 *
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="excerpt", type="string"),
 *     @OA\Property(property="content_html", type="string"),
 *     @OA\Property(property="cover_url", type="string", nullable=true),
 *     @OA\Property(property="hero_image_url", type="string", nullable=true),
 *     @OA\Property(property="hero_image_alt", type="string", nullable=true),
 *     @OA\Property(property="hero_caption", type="string", nullable=true),
 *     @OA\Property(property="author", type="string"),
 *     @OA\Property(property="category", type="string"),
 *     @OA\Property(property="audience_tag", type="string", nullable=true),
 *     @OA\Property(property="reading_time", type="string", nullable=true),
 *     @OA\Property(property="featured", type="boolean"),
 *     @OA\Property(property="trending_score", type="integer", nullable=true),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"draft","published","archived"}),
 *     @OA\Property(
 *         property="seo",
 *         type="object",
 *         @OA\Property(property="title", type="string", nullable=true),
 *         @OA\Property(property="description", type="string", nullable=true),
 *         @OA\Property(property="og_image_url", type="string", nullable=true),
 *         @OA\Property(property="canonical_url", type="string", nullable=true)
 *     ),
 *     @OA\Property(property="toc", type="array", @OA\Items(
 *         @OA\Property(property="label", type="string"),
 *         @OA\Property(property="href", type="string")
 *     )),
 *     @OA\Property(
 *         property="faq",
 *         type="array",
 *
 *         @OA\Items(
 *
 *             @OA\Property(property="question", type="string"),
 *             @OA\Property(property="answer", type="string")
 *         )
 *     ),
 *     @OA\Property(
 *         property="related_posts",
 *         type="array",
 *
 *         @OA\Items(
 *
 *             @OA\Property(property="slug", type="string"),
 *             @OA\Property(property="title", type="string"),
 *             @OA\Property(property="excerpt", type="string"),
 *             @OA\Property(property="cover_url", type="string", nullable=true),
 *             @OA\Property(property="category", type="string"),
 *             @OA\Property(property="author", type="string"),
 *             @OA\Property(property="reading_time", type="string", nullable=true),
 *             @OA\Property(property="published_at", type="string", format="date-time", nullable=true)
 *         )
 *     )
 * )
 */
class BlogPostDetailResourceSchema {}
