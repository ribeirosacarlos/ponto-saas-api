<?php

namespace App\Swagger\Platform;

/**
 * @OA\Tag(
 *     name="Platform - Companies",
 *     description="Rotas de gerenciamento de empresas acessíveis ao super admin"
 * )
 */
class Company {}

/**
 * REGISTRAR EMPRESA + ADMIN
 * ---------------------------------------------------------
 * @OA\Post(
 *     path="/v1/platform/companies/register",
 *     summary="Cria empresa e usuário admin via super admin",
 *     tags={"Platform - Companies"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/PlatformCompanyRegistrationPayload")
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Empresa criada e administrador configurado",
 *         @OA\JsonContent(
 *             @OA\Property(property="company", ref="#/components/schemas/Company"),
 *             @OA\Property(property="admin_user", ref="#/components/schemas/PlatformCompanyRegistrationAdmin")
 *         )
 *     )
 * )
 */
class PlatformCompanyRegister {}


/**
 * LISTAR EMPRESAS
 * ---------------------------------------------------------
 * @OA\Get(
 *     path="/v1/platform/companies",
 *     summary="Lista empresas gerenciadas pela plataforma",
 *     tags={"Platform - Companies"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         description="Filtra por status (active, blocked ou deleted). Valor vazio retorna todas.",
 *         @OA\Schema(type="string", example="active")
 *     ),
 *
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         description="Pesquisa por nome, slug, e-mail ou documento.",
 *         @OA\Schema(type="string", example="empresa")
 *     ),
 *
 *     @OA\Parameter(
 *         name="sort",
 *         in="query",
 *         description="Campo de ordenação, use '-' para ordenar descendente (ex.: '-created_at').",
 *         @OA\Schema(type="string", example="-created_at")
 *     ),
 *
 *     @OA\Parameter(
 *         name="per_page",
 *         in="query",
 *         description="Quantidade de itens por página (1-100)",
 *         @OA\Schema(type="integer", example=20)
 *     ),
 *
 *     @OA\Parameter(
 *         name="page",
 *         in="query",
 *         description="Número da página",
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada de empresas",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=20),
 *             @OA\Property(property="total", type="integer", example=54),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/Company")
 *             )
 *         )
 *     )
 * )
 */
class PlatformCompanyIndex {}


/**
 * CRIAR EMPRESA
 * ---------------------------------------------------------
 * @OA\Post(
 *     path="/v1/platform/companies",
 *     summary="Cria uma nova empresa",
 *     tags={"Platform - Companies"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/PlatformCompanyStore")
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Empresa criada",
 *         @OA\JsonContent(ref="#/components/schemas/Company")
 *     )
 * )
 */
class PlatformCompanyStore {}


/**
 * VER DETALHES DE UMA EMPRESA
 * ---------------------------------------------------------
 * @OA\Get(
 *     path="/v1/platform/companies/{company}",
 *     summary="Exibe os dados de uma empresa específica",
 *     tags={"Platform - Companies"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="company",
 *         in="path",
 *         required=true,
 *         description="ID ou slug da empresa",
 *         @OA\Schema(type="string")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Dados da empresa",
 *         @OA\JsonContent(ref="#/components/schemas/Company")
 *     ),
 *
 *     @OA\Response(response=404, description="Empresa não encontrada")
 * )
 */
class PlatformCompanyShow {}


/**
 * ATUALIZAR EMPRESA
 * ---------------------------------------------------------
 * @OA\Put(
 *     path="/v1/platform/companies/{company}",
 *     summary="Atualiza os dados de uma empresa",
 *     tags={"Platform - Companies"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="company",
 *         in="path",
 *         required=true,
 *         description="ID ou slug da empresa",
 *         @OA\Schema(type="string")
 *     ),
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(ref="#/components/schemas/PlatformCompanyUpdate")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Empresa atualizada",
 *         @OA\JsonContent(ref="#/components/schemas/Company")
 *     )
 * )
 */
class PlatformCompanyUpdate {}


/**
 * DELETAR EMPRESA
 * ---------------------------------------------------------
 * @OA\Delete(
 *     path="/v1/platform/companies/{company}",
 *     summary="Remove uma empresa",
 *     tags={"Platform - Companies"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="company",
 *         in="path",
 *         required=true,
 *         description="ID da empresa",
 *         @OA\Schema(type="string")
 *     ),
 *
 *     @OA\Response(
 *         response=204,
 *         description="Empresa removida"
 *     )
 * )
 */
class PlatformCompanyDestroy {}


/**
 * RESTAURAR EMPRESA
 * ---------------------------------------------------------
 * @OA\Post(
 *     path="/v1/platform/companies/{company}/restore",
 *     summary="Restaura uma empresa deletada",
 *     tags={"Platform - Companies"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="company",
 *         in="path",
 *         required=true,
 *         description="ID da empresa",
 *         @OA\Schema(type="string")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Empresa restaurada",
 *         @OA\JsonContent(ref="#/components/schemas/Company")
 *     ),
 *
 *     @OA\Response(response=422, description="Empresa não está deletada")
 * )
 */
class PlatformCompanyRestore {}


/**
 * BLOQUEAR EMPRESA
 * ---------------------------------------------------------
 * @OA\Post(
 *     path="/v1/platform/companies/{company}/block",
 *     summary="Bloqueia o acesso de uma empresa",
 *     tags={"Platform - Companies"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="company",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string")
 *     ),
 *
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\JsonContent(ref="#/components/schemas/PlatformCompanyBlockPayload")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Empresa bloqueada",
 *         @OA\JsonContent(ref="#/components/schemas/Company")
 *     )
 * )
 */
class PlatformCompanyBlock {}


/**
 * DESBLOQUEAR EMPRESA
 * ---------------------------------------------------------
 * @OA\Post(
 *     path="/v1/platform/companies/{company}/unblock",
 *     summary="Remove o bloqueio de uma empresa",
 *     tags={"Platform - Companies"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="company",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string")
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Empresa desbloqueada",
 *         @OA\JsonContent(ref="#/components/schemas/Company")
 *     )
 * )
 */
class PlatformCompanyUnblock {}


/**
 * @OA\Schema(
 *     schema="Company",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string", example="Empresa Exemplo"),
 *     @OA\Property(property="slug", type="string", example="empresa-exemplo"),
 *     @OA\Property(property="document", type="string", nullable=true, example="00.000.000/0001-00"),
 *     @OA\Property(property="email", type="string", nullable=true, example="contato@empresa.com"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="+5511999999999"),
 *     @OA\Property(property="address", type="string", nullable=true, example="Rua Principal, 100"),
 *     @OA\Property(property="city", type="string", nullable=true, example="São Paulo"),
 *     @OA\Property(property="state", type="string", nullable=true, example="SP"),
 *     @OA\Property(property="plan", type="string", nullable=true, example="enterprise"),
 *     @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="subscription_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="is_blocked", type="boolean"),
 *     @OA\Property(property="blocked_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="blocked_reason", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
 * )
 */
class CompanySchema {}


/**
 * @OA\Schema(
 *     schema="PlatformCompanyStore",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", example="Empresa Padrão"),
 *     @OA\Property(property="document", type="string", nullable=true),
 *     @OA\Property(property="email", type="string", format="email", nullable=true),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="address", type="string", nullable=true),
 *     @OA\Property(property="city", type="string", nullable=true),
 *     @OA\Property(property="state", type="string", nullable=true),
 *     @OA\Property(property="plan", type="string", nullable=true),
 *     @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="subscription_ends_at", type="string", format="date-time", nullable=true)
 * )
 */
class PlatformCompanyStoreSchema {}


/**
 * @OA\Schema(
 *     schema="PlatformCompanyUpdate",
 *     @OA\Property(property="name", type="string", nullable=true),
 *     @OA\Property(property="document", type="string", nullable=true),
 *     @OA\Property(property="email", type="string", format="email", nullable=true),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="address", type="string", nullable=true),
 *     @OA\Property(property="city", type="string", nullable=true),
 *     @OA\Property(property="state", type="string", nullable=true),
 *     @OA\Property(property="plan", type="string", nullable=true),
 *     @OA\Property(property="trial_ends_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="subscription_ends_at", type="string", format="date-time", nullable=true)
 * )
 */
class PlatformCompanyUpdateSchema {}


/**
 * @OA\Schema(
 *     schema="PlatformCompanyBlockPayload",
 *     @OA\Property(property="reason", type="string", nullable=true, maxLength=255)
 * )
 */
class PlatformCompanyBlockPayloadSchema {}


/**
 * @OA\Schema(
 *     schema="PlatformCompanyRegistrationPayload",
 *     required={"company_name","admin_name","admin_email","admin_password","admin_password_confirmation"},
 *     @OA\Property(property="company_name", type="string", example="Empresa Nova"),
 *     @OA\Property(property="company_document", type="string", nullable=true, example="00.000.000/0001-00"),
 *     @OA\Property(property="company_email", type="string", format="email", nullable=true, example="contato@empresa.com"),
 *     @OA\Property(property="company_phone", type="string", nullable=true, example="+55 11 99999-9999"),
 *     @OA\Property(property="company_address", type="string", nullable=true, example="Rua dos Testes, 123"),
 *     @OA\Property(property="company_city", type="string", nullable=true, example="São Paulo"),
 *     @OA\Property(property="company_state", type="string", nullable=true, example="SP"),
 *     @OA\Property(property="admin_name", type="string", example="Maria Fernanda"),
 *     @OA\Property(property="admin_email", type="string", format="email", example="maria@empresa.com"),
 *     @OA\Property(property="admin_password", type="string", format="password", example="SenhaSegura123"),
 *     @OA\Property(property="admin_password_confirmation", type="string", format="password", example="SenhaSegura123")
 * )
 */
class PlatformCompanyRegistrationPayloadSchema {}


/**
 * @OA\Schema(
 *     schema="PlatformCompanyRegistrationAdmin",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="email", type="string", format="email")
 * )
 */
class PlatformCompanyRegistrationAdminSchema {}
