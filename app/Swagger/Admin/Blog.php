<?php

namespace App\Swagger\Admin;

// Os endpoints e a tag "Admin - Blog" vivem em app/Swagger/Admin/Blog/*.php.
// Este arquivo ficou só com os schemas abaixo porque Platform/Blog.php e
// Public/Blog.php ainda referenciam (@OA\Schema $ref) esses nomes.

/**
 * @OA\Schema(
 *     schema="BlogTranslatableString",
 *     description="Campo multilíngue com traduções para pt, es e en",
 *     @OA\Property(property="pt", type="string", example="Texto em português"),
 *     @OA\Property(property="es", type="string", example="Texto en español"),
 *     @OA\Property(property="en", type="string", example="Text in English")
 * )
 */
class BlogTranslatableStringSchema {}


/**
 * @OA\Schema(
 *     schema="BlogTocItem",
 *     description="Item do índice (Table of Contents)",
 *     @OA\Property(property="label", type="string", example="Introdução"),
 *     @OA\Property(property="href", type="string", example="#introducao")
 * )
 */
class BlogTocItemSchema {}


/**
 * @OA\Schema(
 *     schema="BlogFaqItemAdmin",
 *     description="Item de FAQ com traduções completas (visão admin)",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="question", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="answer", ref="#/components/schemas/BlogTranslatableString")
 * )
 */
class BlogFaqItemAdminSchema {}


/**
 * @OA\Schema(
 *     schema="BlogRelatedPostSummary",
 *     description="Resumo de post relacionado (visão admin)",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="slug", type="string", example="como-usar-o-ponto"),
 *     @OA\Property(property="title", type="string", example="Como usar o ponto eletrônico")
 * )
 */
class BlogRelatedPostSummarySchema {}


/**
 * @OA\Schema(
 *     schema="BlogPostAdminDetail",
 *     description="Detalhes completos de um post na visão admin, com traduções por idioma",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="slug", type="string", example="como-usar-o-ponto"),
 *     @OA\Property(property="status", type="string", enum={"draft","published","archived"}),
 *     @OA\Property(property="title", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="excerpt", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="content_html", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="hero_image_alt", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="hero_caption", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="seo_title", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="seo_description", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="toc", type="object", description="TOC por idioma — cada chave é um array de BlogTocItem",
 *         @OA\Property(property="pt", type="array", @OA\Items(ref="#/components/schemas/BlogTocItem")),
 *         @OA\Property(property="es", type="array", @OA\Items(ref="#/components/schemas/BlogTocItem")),
 *         @OA\Property(property="en", type="array", @OA\Items(ref="#/components/schemas/BlogTocItem"))
 *     ),
 *     @OA\Property(property="author", type="string", example="João Silva"),
 *     @OA\Property(property="category", type="string", example="produto"),
 *     @OA\Property(property="audience_tag", type="string", nullable=true, example="rh"),
 *     @OA\Property(property="cover_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="hero_image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="og_image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="canonical_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="reading_time", type="string", nullable=true, example="5 min"),
 *     @OA\Property(property="trending_score", type="integer", nullable=true, example=75),
 *     @OA\Property(property="featured", type="boolean", example=false),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="faq", type="array", @OA\Items(ref="#/components/schemas/BlogFaqItemAdmin")),
 *     @OA\Property(property="related_posts", type="array", @OA\Items(ref="#/components/schemas/BlogRelatedPostSummary"))
 * )
 */
class BlogPostAdminDetailSchema {}


/**
 * @OA\Schema(
 *     schema="BlogPostStoreRequest",
 *     required={"slug","author","category","status","title","excerpt"},
 *     description="Payload para criação de post. Campos de tradução requerem pt, es e en.",
 *     @OA\Property(property="slug", type="string", example="como-usar-o-ponto", description="Apenas letras minúsculas, números e hífens. Único."),
 *     @OA\Property(property="author", type="string", example="João Silva"),
 *     @OA\Property(property="category", type="string", example="produto"),
 *     @OA\Property(property="status", type="string", enum={"draft","published","archived"}),
 *     @OA\Property(property="title", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="excerpt", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="content_html", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="hero_image_alt", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="hero_caption", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="seo_title", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="seo_description", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="toc", type="object",
 *         @OA\Property(property="pt", type="array", @OA\Items(ref="#/components/schemas/BlogTocItem")),
 *         @OA\Property(property="es", type="array", @OA\Items(ref="#/components/schemas/BlogTocItem")),
 *         @OA\Property(property="en", type="array", @OA\Items(ref="#/components/schemas/BlogTocItem"))
 *     ),
 *     @OA\Property(property="cover_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="hero_image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="og_image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="canonical_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="audience_tag", type="string", nullable=true, example="rh"),
 *     @OA\Property(property="reading_time", type="string", nullable=true, example="5 min"),
 *     @OA\Property(property="trending_score", type="integer", nullable=true, minimum=0, maximum=100, example=50),
 *     @OA\Property(property="featured", type="boolean", nullable=true, example=false),
 *     @OA\Property(property="published_at", type="string", format="date", nullable=true, example="2026-06-16"),
 *     @OA\Property(property="faq", type="array", nullable=true,
 *         @OA\Items(
 *             required={"question","answer"},
 *             @OA\Property(property="question", ref="#/components/schemas/BlogTranslatableString"),
 *             @OA\Property(property="answer", ref="#/components/schemas/BlogTranslatableString")
 *         )
 *     ),
 *     @OA\Property(property="related_post_ids", type="array", nullable=true,
 *         @OA\Items(type="string", format="uuid")
 *     )
 * )
 */
class BlogPostStoreRequestSchema {}


/**
 * @OA\Schema(
 *     schema="BlogPostUpdateRequest",
 *     description="Payload para atualização de post. Todos os campos são opcionais (PATCH semântico via PUT). FAQ e related_post_ids substituem inteiramente quando enviados.",
 *     @OA\Property(property="slug", type="string", example="novo-slug-do-post"),
 *     @OA\Property(property="author", type="string", example="João Silva"),
 *     @OA\Property(property="category", type="string", example="produto"),
 *     @OA\Property(property="status", type="string", enum={"draft","published","archived"}),
 *     @OA\Property(property="title", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="excerpt", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="content_html", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="hero_image_alt", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="hero_caption", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="seo_title", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="seo_description", ref="#/components/schemas/BlogTranslatableString"),
 *     @OA\Property(property="toc", type="object",
 *         @OA\Property(property="pt", type="array", @OA\Items(ref="#/components/schemas/BlogTocItem")),
 *         @OA\Property(property="es", type="array", @OA\Items(ref="#/components/schemas/BlogTocItem")),
 *         @OA\Property(property="en", type="array", @OA\Items(ref="#/components/schemas/BlogTocItem"))
 *     ),
 *     @OA\Property(property="cover_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="hero_image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="og_image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="canonical_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="audience_tag", type="string", nullable=true),
 *     @OA\Property(property="reading_time", type="string", nullable=true),
 *     @OA\Property(property="trending_score", type="integer", nullable=true, minimum=0, maximum=100),
 *     @OA\Property(property="featured", type="boolean", nullable=true),
 *     @OA\Property(property="published_at", type="string", format="date", nullable=true),
 *     @OA\Property(property="faq", type="array", nullable=true,
 *         @OA\Items(
 *             @OA\Property(property="question", ref="#/components/schemas/BlogTranslatableString"),
 *             @OA\Property(property="answer", ref="#/components/schemas/BlogTranslatableString")
 *         )
 *     ),
 *     @OA\Property(property="related_post_ids", type="array", nullable=true,
 *         @OA\Items(type="string", format="uuid")
 *     )
 * )
 */
class BlogPostUpdateRequestSchema {}
