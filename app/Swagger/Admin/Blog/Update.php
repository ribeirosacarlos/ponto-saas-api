<?php

namespace App\Swagger\Admin\Blog;

/**
 * @OA\Put(
 *     path="/v1/admin/blog/posts/{id}",
 *     summary="Atualiza um post do blog",
 *     description="Aceita os mesmos campos do cadastro (parcialmente). Campos traduzíveis devem ser enviados como objeto com as chaves pt, es e en.",
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
 *     @OA\RequestBody(
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="slug", type="string"),
 *             @OA\Property(property="author", type="string"),
 *             @OA\Property(property="category", type="string"),
 *             @OA\Property(property="status", type="string", enum={"draft","published","archived"}),
 *             @OA\Property(property="cover_url", type="string", nullable=true),
 *             @OA\Property(property="hero_image_url", type="string", nullable=true),
 *             @OA\Property(property="audience_tag", type="string", nullable=true),
 *             @OA\Property(property="reading_time", type="string", nullable=true),
 *             @OA\Property(property="trending_score", type="integer", nullable=true),
 *             @OA\Property(property="featured", type="boolean", nullable=true),
 *             @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="og_image_url", type="string", nullable=true),
 *             @OA\Property(property="canonical_url", type="string", nullable=true),
 *             @OA\Property(property="title", type="object", description="Traduzível (pt, es, en)"),
 *             @OA\Property(property="excerpt", type="object", description="Traduzível (pt, es, en)"),
 *             @OA\Property(property="content_html", type="object", description="Traduzível (pt, es, en)"),
 *             @OA\Property(
 *                 property="faq",
 *                 type="array",
 *
 *                 @OA\Items(
 *
 *                     @OA\Property(property="question", type="object"),
 *                     @OA\Property(property="answer", type="object")
 *                 )
 *             ),
 *             @OA\Property(
 *                 property="related_post_ids",
 *                 type="array",
 *
 *                 @OA\Items(type="string", format="uuid")
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Atualizado",
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
class Update {}
