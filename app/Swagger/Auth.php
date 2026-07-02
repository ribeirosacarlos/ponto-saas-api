<?php

namespace App\Swagger;

/**
 * @OA\Tag(
 *     name="Auth",
 *     description="User authentication"
 * )
 */
class Auth {}

/**
 * @OA\Schema(
 *     schema="AuthenticatedCompanySummary",
 *     type="object",
 *     description="Resumo seguro da empresa. IDs de cobrança e configurações internas não são expostos.",
 *
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="timezone", type="string"),
 *     @OA\Property(property="timeZone", type="string"),
 *     @OA\Property(property="country", type="string", nullable=true),
 *     @OA\Property(property="locale", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="AuthenticatedUser",
 *     type="object",
 *     description="Usuário autenticado em allowlist; hashes, tokens e campos de convite nunca são retornados.",
 *
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="company_id", type="string", format="uuid", nullable=true),
 *     @OA\Property(property="area_id", type="string", format="uuid", nullable=true),
 *     @OA\Property(property="must_change_password", type="boolean"),
 *     @OA\Property(property="timezone", type="string"),
 *     @OA\Property(property="timeZone", type="string"),
 *     @OA\Property(property="company", ref="#/components/schemas/AuthenticatedCompanySummary", nullable=true)
 * )
 */
class AuthenticatedUserSchema {}

/**
 * ==========================================
 * LOGIN
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/auth/login",
 *     summary="Performs user login",
 *     description="Retorna token Sanctum com validade de 30 dias e dados do usuário em allowlist.",
 *     tags={"Auth"},

 *
 *     @OA\RequestBody(
 *         required=true,
 *         description="Login credentials",
 *
 *         @OA\JsonContent(
 *             required={"email","password"},
 *
 *             @OA\Property(property="email", type="string", format="email", example="ana.pereira@empresa.com"),
 *             @OA\Property(property="password", type="string", example="123456")
 *         )
 *     ),

 *
 *     @OA\Response(
 *         response=200,
 *         description="Login successful",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="user", ref="#/components/schemas/AuthenticatedUser"),
 *             @OA\Property(property="roles", type="array",
 *
 *                 @OA\Items(type="string"),
 *                 example={"employee","admin"}
 *             ),
 *
 *             @OA\Property(property="token", type="string", example="2|xjfi3290jf0239jf0asd0f9as..."),
 *             @OA\Property(property="expires_in", type="integer", example=2592000, description="Validade do token em segundos (30 dias).")
 *         )
 *     ),

 *
 *     @OA\Response(
 *         response=401,
 *         description="Invalid credentials",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="message", type="string", example="Invalid credentials")
 *         )
 *     )
 * )
 */
class AuthLogin {}

/**
 * @OA\Get(
 *     path="/v1/auth/me",
 *     summary="Retorna a sessão autenticada em formato seguro",
 *     tags={"Auth"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(response=200, description="Sessão atual", @OA\JsonContent(
 *
 *         @OA\Property(property="timezone", type="string"),
 *         @OA\Property(property="timeZone", type="string"),
 *         @OA\Property(property="data", type="object",
 *             @OA\Property(property="user", ref="#/components/schemas/AuthenticatedUser"),
 *             @OA\Property(property="roles", type="array", @OA\Items(type="string"))
 *         )
 *     )),
 *
 *     @OA\Response(response=401, description="Token ausente, revogado ou expirado")
 * )
 */
class AuthMe {}

/**
 * ==========================================
 * LOGOUT
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/auth/logout",
 *     summary="Performs logout for authenticated user",
 *     description="Revoga o token Sanctum atual no servidor.",
 *     tags={"Auth"},
 *     security={{"bearerAuth":{}}},

 *
 *     @OA\Response(
 *         response=200,
 *         description="Logout successful",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="message", type="string", example="Logged out successfully")
 *         )
 *     )
 * )
 */
class AuthLogout {}

/**
 * ==========================================
 * FORGOT PASSWORD
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/forgot-password",
 *     summary="Request password reset",
 *     description="Enfileira a recuperação e sempre retorna resposta neutra, exista ou não uma conta.",
 *     tags={"Auth"},

 *
 *     @OA\RequestBody(
 *         required=true,
 *         description="Email for password reset",
 *
 *         @OA\JsonContent(
 *             required={"email"},
 *
 *             @OA\Property(property="email", type="string", format="email", example="user@company.com")
 *         )
 *     ),

 *
 *     @OA\Response(
 *         response=200,
 *         description="Reset code sent successfully",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="message", type="string", example="If the email exists, a recovery link has been sent.")
 *         )
 *     ),

 *
 *     @OA\Response(
 *         response=422,
 *         description="Invalid input data",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="message", type="string", example="The email field is required.")
 *         )
 *     )
 * )
 */
class ForgotPassword {}

/**
 * ==========================================
 * RESET PASSWORD
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/reset-password",
 *     summary="Reset user password",
 *     description="Reset user password with the provided code",
 *     tags={"Auth"},

 *
 *     @OA\RequestBody(
 *         required=true,
 *         description="Data for password reset",
 *
 *         @OA\JsonContent(
 *             required={"token", "email", "password", "password_confirmation"},
 *
 *             @OA\Property(property="token", type="string", example="ABCDEFGH"),
 *             @OA\Property(property="email", type="string", format="email", example="user@company.com"),
 *             @OA\Property(property="password", type="string", example="NewPassword123!"),
 *             @OA\Property(property="password_confirmation", type="string", example="NewPassword123!")
 *         )
 *     ),

 *
 *     @OA\Response(
 *         response=200,
 *         description="Password reset successfully",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="message", type="string", example="Password reset successfully.")
 *         )
 *     ),

 *
 *     @OA\Response(
 *         response=422,
 *         description="Invalid, expired token or invalid data",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="message", type="string", example="Invalid or expired token.")
 *         )
 *     )
 * )
 */
class ResetPassword {}
