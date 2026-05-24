<?php

namespace App\Swagger\Admin;

/**
 * @OA\Tag(
 *     name="Admin - Company Settings",
 *     description="Configurações da empresa: assinaturas de folha, informações cadastrais e localização/idioma"
 * )
 */
class CompanySettings {}

/**
 * ==========================================
 * GET /admin/company/signatures
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/admin/company/signatures",
 *     summary="Retorna as configurações de assinatura de folha de ponto",
 *     tags={"Admin - Company Settings"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Configurações de assinatura",
 *         @OA\JsonContent(
 *             @OA\Property(property="enable_native_signatures", type="boolean", example=false),
 *             @OA\Property(property="require_timesheet_signature", type="boolean", example=false),
 *             @OA\Property(property="require_password_confirmation_for_signature", type="boolean", example=true),
 *             @OA\Property(property="allow_geolocation_on_signature", type="boolean", example=false)
 *         )
 *     ),
 *
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=404, description="Empresa não encontrada")
 * )
 */
class CompanySignatureSettingsShow {}

/**
 * ==========================================
 * PUT|PATCH /admin/company/signatures
 * ==========================================
 *
 * @OA\Put(
 *     path="/v1/admin/company/signatures",
 *     summary="Atualiza as configurações de assinatura de folha de ponto",
 *     description="Todos os campos são opcionais (PATCH semântico). Envie apenas os campos que deseja alterar.",
 *     tags={"Admin - Company Settings"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="enable_native_signatures", type="boolean", example=true),
 *             @OA\Property(property="require_timesheet_signature", type="boolean", example=true),
 *             @OA\Property(property="require_password_confirmation_for_signature", type="boolean", example=true),
 *             @OA\Property(property="allow_geolocation_on_signature", type="boolean", example=false)
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Configurações atualizadas",
 *         @OA\JsonContent(
 *             @OA\Property(property="enable_native_signatures", type="boolean", example=true),
 *             @OA\Property(property="require_timesheet_signature", type="boolean", example=true),
 *             @OA\Property(property="require_password_confirmation_for_signature", type="boolean", example=true),
 *             @OA\Property(property="allow_geolocation_on_signature", type="boolean", example=false)
 *         )
 *     ),
 *
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=403, description="Sem permissão"),
 *     @OA\Response(response=404, description="Empresa não encontrada"),
 *     @OA\Response(response=422, description="Validação falhou")
 * )
 */
class CompanySignatureSettingsUpdate {}

/**
 * ==========================================
 * GET /admin/company/info
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/admin/company/info",
 *     summary="Retorna as informações cadastrais da empresa",
 *     tags={"Admin - Company Settings"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Informações cadastrais",
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="Empresa LTDA"),
 *             @OA\Property(property="email", type="string", format="email", nullable=true, example="contato@empresa.com"),
 *             @OA\Property(property="phone", type="string", nullable=true, example="(11) 91234-5678"),
 *             @OA\Property(property="address", type="string", nullable=true, example="Rua das Flores, 123"),
 *             @OA\Property(property="city", type="string", nullable=true, example="São Paulo"),
 *             @OA\Property(property="state", type="string", nullable=true, example="SP")
 *         )
 *     ),
 *
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=404, description="Empresa não encontrada")
 * )
 */
class CompanyInfoShow {}

/**
 * ==========================================
 * PATCH /admin/company/info
 * ==========================================
 *
 * @OA\Patch(
 *     path="/v1/admin/company/info",
 *     summary="Atualiza as informações cadastrais da empresa",
 *     description="Todos os campos são opcionais. Envie apenas os campos que deseja alterar.",
 *     tags={"Admin - Company Settings"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", maxLength=255, example="Empresa LTDA"),
 *             @OA\Property(property="email", type="string", format="email", nullable=true, example="contato@empresa.com"),
 *             @OA\Property(property="phone", type="string", nullable=true, maxLength=50, example="(11) 91234-5678"),
 *             @OA\Property(property="address", type="string", nullable=true, maxLength=255, example="Rua das Flores, 123"),
 *             @OA\Property(property="city", type="string", nullable=true, maxLength=100, example="São Paulo"),
 *             @OA\Property(property="state", type="string", nullable=true, maxLength=100, example="SP")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Informações atualizadas",
 *         @OA\JsonContent(
 *             @OA\Property(property="name", type="string", example="Empresa LTDA"),
 *             @OA\Property(property="email", type="string", nullable=true, example="contato@empresa.com"),
 *             @OA\Property(property="phone", type="string", nullable=true, example="(11) 91234-5678"),
 *             @OA\Property(property="address", type="string", nullable=true, example="Rua das Flores, 123"),
 *             @OA\Property(property="city", type="string", nullable=true, example="São Paulo"),
 *             @OA\Property(property="state", type="string", nullable=true, example="SP")
 *         )
 *     ),
 *
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=403, description="Sem permissão"),
 *     @OA\Response(response=404, description="Empresa não encontrada"),
 *     @OA\Response(response=422, description="Validação falhou")
 * )
 */
class CompanyInfoUpdate {}

/**
 * ==========================================
 * GET /admin/company/locale
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/admin/company/locale",
 *     summary="Retorna o país e idioma configurados para a empresa",
 *     tags={"Admin - Company Settings"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Configurações de locale",
 *         @OA\JsonContent(
 *             @OA\Property(property="country", type="string", nullable=true, example="BR"),
 *             @OA\Property(property="locale", type="string", nullable=true, example="pt-BR")
 *         )
 *     ),
 *
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=404, description="Empresa não encontrada")
 * )
 */
class CompanyLocaleShow {}

/**
 * ==========================================
 * PUT|PATCH /admin/company/locale
 * ==========================================
 *
 * @OA\Put(
 *     path="/v1/admin/company/locale",
 *     summary="Atualiza o país e idioma da empresa",
 *     description="country aceita código ISO 3166-1 alpha-2 (ex: BR, US). locale aceita código BCP 47 (ex: pt-BR, en-US).",
 *     tags={"Admin - Company Settings"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="country", type="string", nullable=true, maxLength=10, example="BR"),
 *             @OA\Property(property="locale", type="string", nullable=true, maxLength=10, example="pt-BR")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Locale atualizado",
 *         @OA\JsonContent(
 *             @OA\Property(property="country", type="string", nullable=true, example="BR"),
 *             @OA\Property(property="locale", type="string", nullable=true, example="pt-BR")
 *         )
 *     ),
 *
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=403, description="Sem permissão"),
 *     @OA\Response(response=404, description="Empresa não encontrada"),
 *     @OA\Response(response=422, description="Validação falhou")
 * )
 */
class CompanyLocaleUpdate {}
