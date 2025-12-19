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
 *             required={"invite_code","password","password_confirmation"},
 *             @OA\Property(property="invite_code", type="string", description="Código recebido no convite"),
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
 *         description="Código inválido ou expirado",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Código inválido.")
 *         )
 *     )
 * )
 */
class Accept {}
