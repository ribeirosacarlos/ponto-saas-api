<?php

namespace App\Swagger\Platform;

/**
 * @OA\Tag(
 *     name="Platform - Blog",
 *     description="Gerenciamento de posts do blog via painel da plataforma (exclusivo super admin)"
 * )
 */
class PlatformBlogTag {}


/**
 * LISTAR POSTS (PLATFORM)
 * ---------------------------------------------------------
 * @OA\Get(
 *     path="/v1/platform/blog/posts",
 *     summary="Lista todos os posts do blog",
 *     description="Retorna lista paginada de posts, filtrável por status e categoria. Ordenado por updated_at decrescente. Exige role super_admin.",
 *     tags={"Platform - Blog"},
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
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=403, description="Acesso restrito ao super admin")
 * )
 */
class PlatformBlogIndex {}


/**
 * CRIAR POST (PLATFORM)
 * ---------------------------------------------------------
 * @OA\Post(
 *     path="/v1/platform/blog/posts",
 *     summary="Cria um novo post do blog",
 *     description="Cria post com campos multilíngues (pt, es, en). Aceita FAQ e posts relacionados. Exige role super_admin.",
 *     tags={"Platform - Blog"},
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
 *     @OA\Response(response=403, description="Acesso restrito ao super admin"),
 *     @OA\Response(response=422, description="Dados inválidos",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="The slug has already been taken."),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     )
 * )
 */
class PlatformBlogStore {}


/**
 * EXIBIR POST (PLATFORM)
 * ---------------------------------------------------------
 * @OA\Get(
 *     path="/v1/platform/blog/posts/{id}",
 *     summary="Exibe detalhes de um post (visão plataforma)",
 *     description="Retorna todos os campos incluindo traduções completas, FAQ e posts relacionados. Exige role super_admin.",
 *     tags={"Platform - Blog"},
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
 *     @OA\Response(response=403, description="Acesso restrito ao super admin"),
 *     @OA\Response(response=404, description="Post não encontrado")
 * )
 */
class PlatformBlogShow {}


/**
 * ATUALIZAR POST (PLATFORM)
 * ---------------------------------------------------------
 * @OA\Put(
 *     path="/v1/platform/blog/posts/{id}",
 *     summary="Atualiza um post do blog",
 *     description="Atualiza campos do post. FAQ e related_post_ids são substituídos inteiramente quando enviados. Exige role super_admin.",
 *     tags={"Platform - Blog"},
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
 *     @OA\Response(response=403, description="Acesso restrito ao super admin"),
 *     @OA\Response(response=404, description="Post não encontrado"),
 *     @OA\Response(response=422, description="Dados inválidos")
 * )
 */
class PlatformBlogUpdate {}


/**
 * REMOVER POST (PLATFORM)
 * ---------------------------------------------------------
 * @OA\Delete(
 *     path="/v1/platform/blog/posts/{id}",
 *     summary="Remove um post do blog",
 *     description="Exige role super_admin.",
 *     tags={"Platform - Blog"},
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
 *     @OA\Response(response=403, description="Acesso restrito ao super admin"),
 *     @OA\Response(response=404, description="Post não encontrado")
 * )
 */
class PlatformBlogDestroy {}


/**
 * PUBLICAR POST (PLATFORM)
 * ---------------------------------------------------------
 * @OA\Patch(
 *     path="/v1/platform/blog/posts/{id}/publish",
 *     summary="Publica um post",
 *     description="Define status como 'published'. Preenche published_at com a data atual caso ainda não tenha sido definido. Exige role super_admin.",
 *     tags={"Platform - Blog"},
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
 *     @OA\Response(response=403, description="Acesso restrito ao super admin"),
 *     @OA\Response(response=404, description="Post não encontrado")
 * )
 */
class PlatformBlogPublish {}


/**
 * DESPUBLICAR POST (PLATFORM)
 * ---------------------------------------------------------
 * @OA\Patch(
 *     path="/v1/platform/blog/posts/{id}/unpublish",
 *     summary="Despublica um post",
 *     description="Define status como 'draft', removendo o post da listagem pública. Exige role super_admin.",
 *     tags={"Platform - Blog"},
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
 *     @OA\Response(response=403, description="Acesso restrito ao super admin"),
 *     @OA\Response(response=404, description="Post não encontrado")
 * )
 */
class PlatformBlogUnpublish {}
