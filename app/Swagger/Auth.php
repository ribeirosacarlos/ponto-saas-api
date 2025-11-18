<?php

namespace App\Swagger;

/**
 * @OA\Tag(
 *     name="Auth",
 *     description="Autenticação do usuário"
 * )
 */
class Auth {}


/**
 * ==========================================
 * LOGIN
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/auth/login",
 *     summary="Realiza login do usuário",
 *     description="Retorna o token e dados do usuário autenticado",
 *     tags={"Auth"},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         description="Dados de login",
 *         @OA\JsonContent(
 *             required={"email","password"},
 *             @OA\Property(property="email", type="string", format="email", example="usuario@empresa.com"),
 *             @OA\Property(property="password", type="string", example="123456")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Login realizado com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="user", type="object",
 *                 description="Objeto completo do usuário autenticado"
 *             ),
 *             @OA\Property(property="roles", type="array",
 *                 @OA\Items(type="string"),
 *                 example={"employee","admin"}
 *             ),
 *             @OA\Property(property="token", type="string", example="2|xjfi3290jf0239jf0asd0f9as...")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=401,
 *         description="Credenciais inválidas",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Credenciais inválidas")
 *         )
 *     )
 * )
 */
class AuthLogin {}



/**
 * ==========================================
 * LOGOUT
 * ==========================================
 *
 * @OA\Post(
 *     path="/v1/auth/logout",
 *     summary="Realiza logout do usuário autenticado",
 *     description="Revoga o token atual do usuário",
 *     tags={"Auth"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Logout realizado",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Logout feito com sucesso")
 *         )
 *     )
 * )
 */
class AuthLogout {}
