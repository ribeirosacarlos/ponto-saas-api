<?php

namespace App\Swagger\Admin;

/**
 * @OA\Tag(
 *     name="Admin - Blog",
 *     description="Gerenciamento de posts do blog pelo admin autenticado"
 * )
 */
class BlogTag {}


/**
 * LISTAR POSTS (ADMIN)
 * ---------------------------------------------------------
 * @OA\Get(
 *     path="/v1/admin/blog/posts",
 *     summary="Lista todos os posts do blog",
 *     description="Retorna lista paginada de posts, filtrável por status e categoria. Ordenado por updated_at decrescente.",
 *     tags={"Admin - Blog"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         description="Filtra por status do post",
 *         @OA\Schema(type="string", enum={"draft","published","archived"})
 *     ),
 *     @OA\Parameter(
 *         name="category",
 *         in="query",
 *         description="Filtra por categoria (chave da categoria)",
 *         @OA\Schema(type="string", example="produto")
 *     ),
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Itens por página (padrão: 20)",
 *         @OA\Schema(type="integer", example=20)
 *     ),
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Número da página",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada de posts",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BlogPostListItem")),
 *             @OA\Property(property="meta", type="object",
 *                 @OA\Property(property="total", type="integer", example=42),
 *                 @OA\Property(property="page", type="integer", example=1),
 *                 @OA\Property(property="per_page", type="integer", example=20)
 *             )
 *         )
 *     ),
 *     @OA\Response(response=401, description="Não autenticado")
 * )
 */
class AdminBlogIndex {}


/**
 * CRIAR POST
 * ---------------------------------------------------------
 * @OA\Post(
 *     path="/v1/admin/blog/posts",
 *     summary="Cria um novo post do blog",
 *     description="Cria post com campos multilíngues (pt, es, en). Aceita FAQ e posts relacionados.",
 *     tags={"Admin - Blog"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/BlogPostStoreRequest")
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Post criado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", ref="#/components/schemas/BlogPostAdminDetail")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=422, description="Dados inválidos",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="The slug has already been taken."),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     )
 * )
 */
class AdminBlogStore {}


/**
 * EXIBIR POST (ADMIN)
 * ---------------------------------------------------------
 * @OA\Get(
 *     path="/v1/admin/blog/posts/{id}",
 *     summary="Exibe detalhes de um post (visão admin)",
 *     description="Retorna todos os campos incluindo traduções completas, FAQ e posts relacionados.",
 *     tags={"Admin - Blog"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="UUID do post",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Detalhes do post",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", ref="#/components/schemas/BlogPostAdminDetail")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=404, description="Post não encontrado")
 * )
 */
class AdminBlogShow {}


/**
 * ATUALIZAR POST
 * ---------------------------------------------------------
 * @OA\Put(
 *     path="/v1/admin/blog/posts/{id}",
 *     summary="Atualiza um post do blog",
 *     description="Atualiza campos do post. Campos de tradução seguem o formato {pt, es, en}. FAQ e posts relacionados são substituídos inteiramente quando enviados.",
 *     tags={"Admin - Blog"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="UUID do post",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/BlogPostUpdateRequest")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Post atualizado",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", ref="#/components/schemas/BlogPostAdminDetail")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=404, description="Post não encontrado"),
 *     @OA\Response(response=422, description="Dados inválidos")
 * )
 */
class AdminBlogUpdate {}


/**
 * REMOVER POST
 * ---------------------------------------------------------
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
 *         description="UUID do post",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\Response(response=204, description="Post removido"),
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=404, description="Post não encontrado")
 * )
 */
class AdminBlogDestroy {}


/**
 * PUBLICAR POST
 * ---------------------------------------------------------
 * @OA\Patch(
 *     path="/v1/admin/blog/posts/{id}/publish",
 *     summary="Publica um post",
 *     description="Define status como 'published'. Preenche published_at com a data atual caso ainda não tenha sido definido.",
 *     tags={"Admin - Blog"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="UUID do post",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Post publicado",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", ref="#/components/schemas/BlogPostAdminDetail")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=404, description="Post não encontrado")
 * )
 */
class AdminBlogPublish {}


/**
 * DESPUBLICAR POST
 * ---------------------------------------------------------
 * @OA\Patch(
 *     path="/v1/admin/blog/posts/{id}/unpublish",
 *     summary="Despublica um post",
 *     description="Define status como 'draft', removendo o post da listagem pública.",
 *     tags={"Admin - Blog"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="UUID do post",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Post despublicado",
 *         @OA\JsonContent(
 *             @OA\Property(property="data", ref="#/components/schemas/BlogPostAdminDetail")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=404, description="Post não encontrado")
 * )
 */
class AdminBlogUnpublish {}


/**
 * PRESIGNED URL PARA UPLOAD DE IMAGEM
 * ---------------------------------------------------------
 * @OA\Post(
 *     path="/v1/admin/blog/uploads/presign",
 *     summary="Gera URL pré-assinada para upload de imagem no S3",
 *     description="Retorna uma URL temporária (válida por 5 minutos) para enviar a imagem diretamente ao S3 via PUT, e a URL pública final do arquivo.",
 *     tags={"Admin - Blog"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"filename","mime_type","folder"},
 *             @OA\Property(property="filename", type="string", example="hero-imagem.webp", description="Nome original do arquivo"),
 *             @OA\Property(property="mime_type", type="string", enum={"image/jpeg","image/png","image/webp"}, example="image/webp"),
 *             @OA\Property(property="folder", type="string", enum={"covers","heroes","og"}, example="heroes", description="Pasta de destino no bucket S3")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="URLs geradas com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="upload_url", type="string", format="uri", example="https://bucket.s3.amazonaws.com/blog/heroes/01jx...?X-Amz-Signature=...", description="URL pré-assinada para PUT direto ao S3 (expira em 5 min)"),
 *             @OA\Property(property="public_url", type="string", format="uri", example="https://cdn.exemplo.com/blog/heroes/01jx....webp", description="URL pública permanente do arquivo após o upload")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=422, description="Tipo de arquivo ou pasta inválidos")
 * )
 */
class AdminBlogUploadPresign {}


// ============================================================
// SCHEMAS
// ============================================================

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
 *     schema="BlogPostListItem",
 *     description="Item de post na listagem (campos no idioma da requisição)",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="slug", type="string", example="como-usar-o-ponto"),
 *     @OA\Property(property="title", type="string", example="Como usar o ponto eletrônico"),
 *     @OA\Property(property="excerpt", type="string", example="Aprenda a registrar o ponto de forma simples."),
 *     @OA\Property(property="cover_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="category", type="string", example="produto"),
 *     @OA\Property(property="audience_tag", type="string", nullable=true, example="rh"),
 *     @OA\Property(property="author", type="string", example="João Silva"),
 *     @OA\Property(property="reading_time", type="string", nullable=true, example="5 min"),
 *     @OA\Property(property="published_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="featured", type="boolean", example=false),
 *     @OA\Property(property="trending_score", type="integer", example=75)
 * )
 */
class BlogPostListItemSchema {}


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
