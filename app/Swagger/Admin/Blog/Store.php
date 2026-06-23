<?php

namespace App\Swagger\Admin\Blog;

/**
 * @OA\Post(
 *     path="/v1/admin/blog/posts",
 *     summary="Cria um novo post do blog",
 *     description="Campos de texto (title, excerpt, content_html, etc.) são traduzíveis e devem ser enviados como objeto com as chaves pt, es e en.",
 *     tags={"Admin - Blog"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *
 *         @OA\JsonContent(
 *             required={"slug","author","category","status","title","excerpt"},
 *
 *             @OA\Property(property="slug", type="string", example="ponto-eletronico-espanha"),
 *             @OA\Property(property="author", type="string", example="Equipe Jornafy"),
 *             @OA\Property(property="category", type="string", example="compliance"),
 *             @OA\Property(property="status", type="string", enum={"draft","published","archived"}),
 *             @OA\Property(property="source", type="string", enum={"manual","n8n"}, nullable=true, description="Origem do post. 'manual' (padrão) ou 'n8n' quando criado pela automação."),
 *             @OA\Property(property="cover_url", type="string", nullable=true),
 *             @OA\Property(property="hero_image_url", type="string", nullable=true),
 *             @OA\Property(property="audience_tag", type="string", nullable=true, example="rh"),
 *             @OA\Property(property="reading_time", type="string", nullable=true, example="5 min"),
 *             @OA\Property(property="trending_score", type="integer", nullable=true, example=80),
 *             @OA\Property(property="featured", type="boolean", nullable=true),
 *             @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(property="og_image_url", type="string", nullable=true),
 *             @OA\Property(property="canonical_url", type="string", nullable=true),
 *             @OA\Property(
 *                 property="title",
 *                 type="object",
 *                 description="Traduzível (pt, es, en)",
 *                 @OA\Property(property="pt", type="string", example="Como funciona o controle de ponto na Espanha"),
 *                 @OA\Property(property="es", type="string"),
 *                 @OA\Property(property="en", type="string")
 *             ),
 *             @OA\Property(
 *                 property="excerpt",
 *                 type="object",
 *                 description="Traduzível (pt, es, en)",
 *                 @OA\Property(property="pt", type="string"),
 *                 @OA\Property(property="es", type="string"),
 *                 @OA\Property(property="en", type="string")
 *             ),
 *             @OA\Property(
 *                 property="content_html",
 *                 type="object",
 *                 description="Traduzível (pt, es, en)",
 *                 @OA\Property(property="pt", type="string"),
 *                 @OA\Property(property="es", type="string"),
 *                 @OA\Property(property="en", type="string")
 *             ),
 *             @OA\Property(
 *                 property="faq",
 *                 type="array",
 *                 description="Itens de FAQ, com pergunta e resposta traduzíveis",
 *
 *                 @OA\Items(
 *
 *                     @OA\Property(
 *                         property="question",
 *                         type="object",
 *                         @OA\Property(property="pt", type="string"),
 *                         @OA\Property(property="es", type="string"),
 *                         @OA\Property(property="en", type="string")
 *                     ),
 *                     @OA\Property(
 *                         property="answer",
 *                         type="object",
 *                         @OA\Property(property="pt", type="string"),
 *                         @OA\Property(property="es", type="string"),
 *                         @OA\Property(property="en", type="string")
 *                     )
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
 *         response=201,
 *         description="Criado",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="data", ref="#/components/schemas/BlogPostAdminResource")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Erro de validação",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="message", type="string", example="The given data was invalid."),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="slug", type="array",
 *
 *                     @OA\Items(type="string", example="The slug has already been taken.")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class Store {}

/**
 * @OA\Schema(
 *     schema="BlogPostAdminResource",
 *
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="status", type="string", enum={"draft","published","archived"}),
 *     @OA\Property(property="source", type="string", enum={"manual","n8n"}),
 *     @OA\Property(property="title", type="object", @OA\Property(property="pt", type="string"), @OA\Property(property="es", type="string"), @OA\Property(property="en", type="string")),
 *     @OA\Property(property="excerpt", type="object", @OA\Property(property="pt", type="string"), @OA\Property(property="es", type="string"), @OA\Property(property="en", type="string")),
 *     @OA\Property(property="content_html", type="object", @OA\Property(property="pt", type="string"), @OA\Property(property="es", type="string"), @OA\Property(property="en", type="string")),
 *     @OA\Property(property="hero_image_alt", type="object", nullable=true),
 *     @OA\Property(property="hero_caption", type="object", nullable=true),
 *     @OA\Property(property="seo_title", type="object", nullable=true),
 *     @OA\Property(property="seo_description", type="object", nullable=true),
 *     @OA\Property(property="toc", type="object", nullable=true),
 *     @OA\Property(property="author", type="string"),
 *     @OA\Property(property="category", type="string"),
 *     @OA\Property(property="audience_tag", type="string", nullable=true),
 *     @OA\Property(property="cover_url", type="string", nullable=true),
 *     @OA\Property(property="hero_image_url", type="string", nullable=true),
 *     @OA\Property(property="og_image_url", type="string", nullable=true),
 *     @OA\Property(property="canonical_url", type="string", nullable=true),
 *     @OA\Property(property="reading_time", type="string", nullable=true),
 *     @OA\Property(property="trending_score", type="integer", nullable=true),
 *     @OA\Property(property="featured", type="boolean"),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(
 *         property="faq",
 *         type="array",
 *
 *         @OA\Items(
 *
 *             @OA\Property(property="id", type="string", format="uuid"),
 *             @OA\Property(property="question", type="object"),
 *             @OA\Property(property="answer", type="object")
 *         )
 *     ),
 *     @OA\Property(
 *         property="related_posts",
 *         type="array",
 *
 *         @OA\Items(
 *
 *             @OA\Property(property="id", type="string", format="uuid"),
 *             @OA\Property(property="slug", type="string"),
 *             @OA\Property(property="title", type="string")
 *         )
 *     )
 * )
 */
class BlogPostAdminResourceSchema {}
