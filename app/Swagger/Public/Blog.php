<?php

namespace App\Swagger\PublicBlog;

/**
 * @OA\Tag(
 *     name="Public - Blog",
 *     description="Endpoints públicos do blog (sem autenticação)"
 * )
 */
class PublicBlogTag {}


/**
 * LISTAR POSTS PUBLICADOS
 * ---------------------------------------------------------
 * @OA\Get(
 *     path="/v1/public/blog/posts",
 *     summary="Lista posts publicados do blog",
 *     description="Retorna lista paginada de posts com status 'published'. Suporta filtragem por categoria, destaque e busca por título e excerpt. Campos de texto são retornados no idioma solicitado.",
 *     tags={"Public - Blog"},
 *
 *     @OA\Parameter(
 *         name="lang",
 *         in="query",
 *         description="Idioma dos campos de texto (padrão: es)",
 *         @OA\Schema(type="string", enum={"pt","es","en"}, default="es")
 *     ),
 *     @OA\Parameter(
 *         name="category",
 *         in="query",
 *         description="Filtra posts por chave de categoria",
 *         @OA\Schema(type="string", example="produto")
 *     ),
 *     @OA\Parameter(
 *         name="featured",
 *         in="query",
 *         description="Filtra apenas posts em destaque",
 *         @OA\Schema(type="boolean", example=true)
 *     ),
 *     @OA\Parameter(
 *         name="q",
 *         in="query",
 *         description="Busca textual no título e excerpt no idioma solicitado (máx. 100 caracteres)",
 *         @OA\Schema(type="string", maxLength=100, example="ponto eletrônico")
 *     ),
 *     @OA\Parameter(
 *         name="sort",
 *         in="query",
 *         description="Ordenação dos resultados (padrão: published_at_desc)",
 *         @OA\Schema(type="string", enum={"published_at_desc","trending_score_desc"}, default="published_at_desc")
 *     ),
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Número da página (mín: 1)",
 *         @OA\Schema(type="integer", minimum=1, example=1)
 *     ),
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Itens por página (padrão: 9, máx: 50)",
 *         @OA\Schema(type="integer", minimum=1, maximum=50, default=9)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada de posts publicados",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BlogPostListItem")),
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="total", type="integer", example=38),
 *                 @OA\Property(property="page", type="integer", example=1),
 *                 @OA\Property(property="per_page", type="integer", example=9)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=422, description="Parâmetros inválidos",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="The sort field must be one of: published_at_desc, trending_score_desc."),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     )
 * )
 */
class PublicBlogIndex {}


/**
 * EXIBIR POST POR SLUG
 * ---------------------------------------------------------
 * @OA\Get(
 *     path="/v1/public/blog/posts/{slug}",
 *     summary="Exibe um post publicado pelo slug",
 *     description="Retorna o conteúdo completo do post incluindo HTML, TOC, FAQ e posts relacionados. Campos de texto são retornados no idioma solicitado.",
 *     tags={"Public - Blog"},
 *
 *     @OA\Parameter(
 *         name="slug",
 *         in="path",
 *         required=true,
 *         description="Slug único do post",
 *         @OA\Schema(type="string", example="como-usar-o-ponto-eletronico")
 *     ),
 *     @OA\Parameter(
 *         name="lang",
 *         in="query",
 *         description="Idioma dos campos de texto (padrão: es)",
 *         @OA\Schema(type="string", enum={"pt","es","en"}, default="es")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Detalhes completos do post",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", ref="#/components/schemas/BlogPostPublicDetail")
 *         )
 *     ),
 *     @OA\Response(response=404, description="Post não encontrado ou não publicado")
 * )
 */
class PublicBlogShow {}


/**
 * LISTAR CATEGORIAS
 * ---------------------------------------------------------
 * @OA\Get(
 *     path="/v1/public/blog/categories",
 *     summary="Lista categorias disponíveis do blog",
 *     description="Retorna todas as categorias ordenadas por sort_order, com labels em pt, es e en.",
 *     tags={"Public - Blog"},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista de categorias",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="array",
 *                 @OA\Items(ref="#/components/schemas/BlogCategory")
 *             )
 *         )
 *     )
 * )
 */
class PublicBlogCategories {}


// ============================================================
// SCHEMAS
// ============================================================

/**
 * @OA\Schema(
 *     schema="BlogCategory",
 *     description="Categoria do blog com labels multilíngues",
 *     @OA\Property(property="key", type="string", example="produto", description="Identificador único da categoria"),
 *     @OA\Property(property="label_pt", type="string", example="Produto"),
 *     @OA\Property(property="label_es", type="string", example="Producto"),
 *     @OA\Property(property="label_en", type="string", example="Product")
 * )
 */
class BlogCategorySchema {}


/**
 * @OA\Schema(
 *     schema="BlogFaqItemPublic",
 *     description="Item de FAQ com texto no idioma da requisição",
 *     @OA\Property(property="question", type="string", example="Como funciona o registro de ponto?"),
 *     @OA\Property(property="answer", type="string", example="O registro é feito via aplicativo...")
 * )
 */
class BlogFaqItemPublicSchema {}


/**
 * @OA\Schema(
 *     schema="BlogRelatedPostPublic",
 *     description="Resumo de post relacionado na visão pública",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="slug", type="string", example="guia-de-ferias"),
 *     @OA\Property(property="title", type="string", example="Guia completo de férias"),
 *     @OA\Property(property="excerpt", type="string", example="Tudo que você precisa saber sobre férias..."),
 *     @OA\Property(property="cover_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="category", type="string", example="rh"),
 *     @OA\Property(property="audience_tag", type="string", nullable=true, example="rh"),
 *     @OA\Property(property="author", type="string", example="Maria Silva"),
 *     @OA\Property(property="reading_time", type="string", nullable=true, example="7 min"),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true)
 * )
 */
class BlogRelatedPostPublicSchema {}


/**
 * @OA\Schema(
 *     schema="BlogPostPublicDetail",
 *     description="Detalhes completos de um post na visão pública. Campos de texto retornados no idioma solicitado via ?lang=.",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="slug", type="string", example="como-usar-o-ponto-eletronico"),
 *     @OA\Property(property="title", type="string", example="Como usar o ponto eletrônico"),
 *     @OA\Property(property="excerpt", type="string", example="Aprenda a registrar o ponto de forma simples."),
 *     @OA\Property(property="content_html", type="string", description="Conteúdo do post em HTML"),
 *     @OA\Property(property="cover_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="hero_image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="hero_image_alt", type="string", nullable=true, example="Funcionário registrando ponto no app"),
 *     @OA\Property(property="hero_caption", type="string", nullable=true),
 *     @OA\Property(property="author", type="string", example="João Silva"),
 *     @OA\Property(property="category", type="string", example="produto"),
 *     @OA\Property(property="audience_tag", type="string", nullable=true, example="rh"),
 *     @OA\Property(property="reading_time", type="string", nullable=true, example="5 min"),
 *     @OA\Property(property="featured", type="boolean", example=false),
 *     @OA\Property(property="trending_score", type="integer", example=75),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="seo_title", type="string", nullable=true, example="Como usar o ponto | Ponto SaaS"),
 *     @OA\Property(property="seo_description", type="string", nullable=true, example="Guia completo para registro de ponto..."),
 *     @OA\Property(property="og_image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="canonical_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="toc", type="array",
 *         description="Índice do post no idioma solicitado",
 *         @OA\Items(ref="#/components/schemas/BlogTocItem")
 *     ),
 *     @OA\Property(property="faq", type="array",
 *         @OA\Items(ref="#/components/schemas/BlogFaqItemPublic")
 *     ),
 *     @OA\Property(property="related_posts", type="array",
 *         @OA\Items(ref="#/components/schemas/BlogRelatedPostPublic")
 *     )
 * )
 */
class BlogPostPublicDetailSchema {}
