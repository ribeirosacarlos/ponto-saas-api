<?php

namespace App\Swagger\Employee;

/**
 * @OA\Tag(
 *     name="Employee - Profile",
 *     description="Configurações de perfil do usuário autenticado"
 * )
 */
class Profile {}

/**
 * ==========================================
 * Atualizar nome do perfil
 * ==========================================
 *
 * @OA\Patch(
 *     path="/v1/employee/profile",
 *     summary="Atualiza o nome do usuário autenticado",
 *     description="Atualiza o campo name do próprio usuário. O usuário é identificado pelo Bearer token — nenhum ID na URL. Disponível para employee, area_manager, manager e admin.",
 *     tags={"Employee - Profile"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"name"},
 *             @OA\Property(property="name", type="string", maxLength=255, example="Ana Pereira")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Perfil atualizado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="user", type="object",
 *                 @OA\Property(property="id", type="string", format="uuid", example="018f1a2b-3c4d-5e6f-7a8b-9c0d1e2f3a4b"),
 *                 @OA\Property(property="name", type="string", example="Ana Pereira"),
 *                 @OA\Property(property="email", type="string", format="email", example="ana@empresa.com")
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Validação falhou",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="O campo nome é obrigatório."),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="name", type="array",
 *                     @OA\Items(type="string", example="O campo nome é obrigatório.")
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(response=401, description="Não autenticado")
 * )
 */
class ProfileUpdate {}

/**
 * ==========================================
 * Trocar senha
 * ==========================================
 *
 * @OA\Put(
 *     path="/v1/employee/password",
 *     summary="Troca a senha do usuário autenticado",
 *     description="Verifica a senha atual antes de aplicar a nova. O usuário é identificado pelo Bearer token. Disponível para employee, area_manager, manager e admin.",
 *     tags={"Employee - Profile"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"current_password","password","password_confirmation"},
 *             @OA\Property(property="current_password", type="string", example="senha-atual-123"),
 *             @OA\Property(property="password", type="string", minLength=8, example="nova-senha-456"),
 *             @OA\Property(property="password_confirmation", type="string", example="nova-senha-456")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Senha trocada com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Senha atualizada com sucesso.")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Senha atual incorreta ou confirmação não confere",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="A senha atual está incorreta."),
 *             @OA\Property(property="errors", type="object",
 *                 @OA\Property(property="current_password", type="array",
 *                     @OA\Items(type="string", example="A senha atual está incorreta.")
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(response=401, description="Não autenticado")
 * )
 */
class ProfileUpdatePassword {}
