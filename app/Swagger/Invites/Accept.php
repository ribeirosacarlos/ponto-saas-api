<?php

namespace App\Swagger\Invites;

/**
 * @OA\Post(
 *     path="/v1/invites/accept",
 *     summary="Aceita o convite enviado pelo administrador e define uma nova senha",
 *     tags={"Invites"},
 *
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"token","password","password_confirmation"},
 *             @OA\Property(property="token", type="string", description="Token recebido no convite"),
 *             @OA\Property(property="password", type="string", minLength=8, example="nova-senha-segura"),
 *             @OA\Property(property="password_confirmation", type="string", minLength=8, example="nova-senha-segura")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Senha criada com sucesso",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Senha definida com sucesso.")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Token inválido ou expirado",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Token inválido.")
 *         )
 *     )
 * )
 */
class Accept {}
