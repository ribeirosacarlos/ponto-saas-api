<?php

namespace App\Swagger;

/**
 * @OA\Tag(
 *     name="Documents",
 *     description="Gerencia os documentos vinculados aos funcionários."
 * )
 *
 * @OA\Schema(
 *     schema="DocumentResource",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="category", type="string", enum={"payroll","courses","personal","others"}),
 *     @OA\Property(property="status", type="string", enum={"pending","review","available","expired"}),
 *     @OA\Property(property="size_bytes", type="integer"),
 *     @OA\Property(property="mime_type", type="string"),
 *     @OA\Property(property="ext", type="string"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="view_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="download_url", type="string", format="uri", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="DocumentAdminResource",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/DocumentResource"),
 *         @OA\Schema(
 *             @OA\Property(property="employee", type="object",
 *                 @OA\Property(property="id", type="string", format="uuid"),
 *                 @OA\Property(property="name", type="string"),
 *                 @OA\Property(property="email", type="string")
 *             ),
 *             @OA\Property(property="rejected_comment", type="string", nullable=true),
 *             @OA\Property(property="rejected_by", type="string", nullable=true, format="uuid"),
 *             @OA\Property(property="rejected_at", type="string", nullable=true, format="date-time")
 *         )
 *     }
 * )
 *
 * @OA\Get(
 *     path="/v1/documents",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Lista os documentos do funcionário logado",
 *     description="Permite filtrar por categoria, status, busca e paginar. O filtro user_id só é respeitado por funções privilegiadas.",
 *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string", enum={"payroll","courses","personal","others"})),
 *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"pending","review","available","expired"})),
 *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", description="Busca parcial no título ou notas")),
 *     @OA\Parameter(name="user_id", in="query", required=false, @OA\Schema(type="string", format="uuid", description="Disponível apenas para admins/gerentes/area managers")),
 *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", example=1)),
 *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
 *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", example="updated_at:desc")),
 *     @OA\Response(response=200, description="Lista paginada de documentos", @OA\JsonContent(
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="per_page", type="integer", example=20),
 *         @OA\Property(property="last_page", type="integer", example=5),
 *         @OA\Property(property="total", type="integer", example=80),
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DocumentResource"))
 *     )),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=422, description="Parâmetros inválidos")
 * )
 *
 * @OA\Post(
 *     path="/v1/documents",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Envio de um ou mais documentos",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"category","files"},
 *                 @OA\Property(property="category", type="string", enum={"payroll","courses","personal","others"}),
 *                 @OA\Property(property="title", type="string", maxLength=180),
 *                 @OA\Property(property="notes", type="string", maxLength=2000),
 *                 @OA\Property(property="files[]", type="string", format="binary", description="Multipart com os arquivos permitidos")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="Documentos criados", @OA\JsonContent(
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DocumentResource"))
 *     )),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=422, description="Dados inválidos ou arquivo muito grande")
 * )
 *
 * @OA\Get(
 *     path="/v1/documents/{document}",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Detalhes de um documento",
 *     @OA\Parameter(name="document", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Documento retornado", @OA\JsonContent(ref="#/components/schemas/DocumentResource")),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=404, description="Documento não encontrado")
 * )
 *
 * @OA\Get(
 *     path="/v1/documents/{document}/view",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Abre o documento em modo inline",
 *     @OA\Parameter(name="document", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Stream do arquivo"),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=404, description="Arquivo não encontrado")
 * )
 *
 * @OA\Get(
 *     path="/v1/documents/{document}/download",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Baixa o documento como anexo",
 *     @OA\Parameter(name="document", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Download do arquivo"),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=404, description="Arquivo não encontrado")
 * )
 *
 * @OA\Post(
 *     path="/v1/documents/{document}/resend",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Reenvia o documento rejeitado",
 *     @OA\Parameter(name="document", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"file"},
 *                 @OA\Property(property="file", type="string", format="binary")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=200, description="Documento reenviado", @OA\JsonContent(ref="#/components/schemas/DocumentResource")),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=422, description="Documento não está em revisão")
 * )
 *
 * @OA\Patch(
 *     path="/v1/documents/{document}",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Atualiza metadados ou status do documento",
 *     @OA\Parameter(name="document", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="title", type="string", maxLength=180),
 *             @OA\Property(property="category", type="string", enum={"payroll","courses","personal","others"}),
 *             @OA\Property(property="status", type="string", enum={"pending","review","available","expired"}),
 *             @OA\Property(property="notes", type="string", maxLength=2000)
 *         )
 *     ),
 *     @OA\Response(response=200, description="Documento atualizado", @OA\JsonContent(ref="#/components/schemas/DocumentResource")),
 *     @OA\Response(response=400, description="Dados inválidos"),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=404, description="Documento não encontrado")
 * )
 *
 * @OA\Patch(
 *     path="/v1/documents/{document}/approve",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Marca o documento como disponível",
 *     @OA\Parameter(name="document", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Documento aprovado", @OA\JsonContent(ref="#/components/schemas/DocumentResource")),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=404, description="Documento não encontrado")
 * )
 *
 * @OA\Delete(
 *     path="/v1/documents/{document}",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Remove um documento",
 *     @OA\Parameter(name="document", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=204, description="Documento removido"),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=404, description="Documento não encontrado")
 * )
 *
 * @OA\Get(
 *     path="/v1/admin/documents/pending",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Lista documentos pendentes para revisão",
 *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string", enum={"payroll","courses","personal","others"})),
 *     @OA\Parameter(name="employee_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
 *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", example=1)),
 *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
 *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", example="updated_at:desc")),
 *     @OA\Response(response=200, description="Documentos pendentes", @OA\JsonContent(
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="per_page", type="integer", example=20),
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DocumentAdminResource"))
 *     )),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado")
 * )
 *
 * @OA\Get(
 *     path="/v1/admin/documents/review",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Lista documentos rejeitados aguardando reenvio",
 *     @OA\Parameter(name="category", in="query", required=false, @OA\Schema(type="string", enum={"payroll","courses","personal","others"})),
 *     @OA\Parameter(name="employee_id", in="query", required=false, @OA\Schema(type="string", format="uuid")),
 *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
 *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", example=1)),
 *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", example=20)),
 *     @OA\Parameter(name="sort", in="query", required=false, @OA\Schema(type="string", example="updated_at:desc")),
 *     @OA\Response(response=200, description="Documentos em revisão", @OA\JsonContent(
 *         @OA\Property(property="current_page", type="integer", example=1),
 *         @OA\Property(property="per_page", type="integer", example=20),
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DocumentAdminResource"))
 *     )),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado")
 * )
 *
 * @OA\Get(
 *     path="/v1/admin/documents/{document}",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Detalhes administrativos de um documento",
 *     @OA\Parameter(name="document", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Documento retornado", @OA\JsonContent(ref="#/components/schemas/DocumentAdminResource")),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=404, description="Documento não encontrado")
 * )
 *
 * @OA\Patch(
 *     path="/v1/admin/documents/{document}/approve",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Aprova um documento e limpa o estado de rejeição",
 *     @OA\Parameter(name="document", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Documento aprovado", @OA\JsonContent(ref="#/components/schemas/DocumentAdminResource")),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=404, description="Documento não encontrado")
 * )
 *
 * @OA\Patch(
 *     path="/v1/admin/documents/{document}/reject",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Rejeita um documento com comentário",
 *     @OA\Parameter(name="document", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="comment", type="string", minLength=5, maxLength=2000)
 *         )
 *     ),
 *     @OA\Response(response=200, description="Documento rejeitado", @OA\JsonContent(ref="#/components/schemas/DocumentAdminResource")),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=404, description="Documento não encontrado"),
 *     @OA\Response(response=422, description="Comentário obrigatório")
 * )
 *
 * @OA\Post(
 *     path="/v1/admin/documents/upload-for-employee",
 *     tags={"Documents"},
 *     security={{"bearerAuth": {}}},
 *     summary="Upload de documentos para funcionário (apenas admin)",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"user_id","category","files"},
 *                 @OA\Property(property="user_id", type="string", format="uuid", description="ID do funcionário"),
 *                 @OA\Property(property="category", type="string", enum={"payroll","courses","personal","others"}),
 *                 @OA\Property(property="title", type="string", maxLength=180),
 *                 @OA\Property(property="notes", type="string", maxLength=2000),
 *                 @OA\Property(property="files[]", type="string", format="binary", description="Multipart com os arquivos permitidos")
 *             )
 *         )
 *     ),
 *     @OA\Response(response=201, description="Documentos criados", @OA\JsonContent(
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DocumentAdminResource"))
 *     )),
 *     @OA\Response(response=400, description="Dados inválidos"),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=403, description="Acesso negado"),
 *     @OA\Response(response=404, description="Funcionário não encontrado"),
 *     @OA\Response(response=422, description="Dados inválidos ou arquivo muito grande")
 * )
 */
class Documents {}
