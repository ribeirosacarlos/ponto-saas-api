<?php

namespace App\Swagger\Debug;

/**
 * @OA\Tag(
 *     name="Debug",
 *     description="Rotas auxiliares para facilitar testes e inspeções locais"
 * )
 */

/**
 * @OA\Get(
 *     path="/v1/debug/send-employee-invite-job",
 *     summary="Cria o job SendEmployeeInviteJob para testes de filas",
 *     tags={"Debug"},
 *     @OA\Parameter(
 *         name="send_email",
 *         in="query",
 *         description="Determina se o job deve ser gerado (valores verdadeiros: sim, s, yes, y, true, 1)",
 *         required=false,
 *         @OA\Schema(type="string", example="sim")
 *     ),
 *     @OA\Parameter(
 *         name="enviar_email",
 *         in="query",
 *         description="Alias em português para 'send_email'",
 *         required=false,
 *         @OA\Schema(type="string", example="sim")
 *     ),
 *     @OA\Parameter(
 *         name="user_id",
 *         in="query",
 *         description="UUID do usuário que deve receber o convite (usar para direcionar o job)",
 *         required=false,
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *     @OA\Parameter(
 *         name="recipient_email",
 *         in="query",
 *         description="Email que deve receber o convite (padrão dev.carlosdesa@gmail.com)",
 *         required=false,
 *         @OA\Schema(type="string", format="email", example="dev.carlosdesa@gmail.com")
 *     ),
 *     @OA\Parameter(
 *         name="override_email",
 *         in="query",
 *         description="Alias para recipient_email",
 *         required=false,
 *         @OA\Schema(type="string", format="email")
 *     ),
 *     @OA\Parameter(
 *         name="invite_url",
 *         in="query",
 *         description="URL de convite usada pelo payload (caso não seja configurada, usa config('app.invite_url'))",
 *         required=false,
 *         @OA\Schema(type="string", format="uri")
 *     ),
 *     @OA\Parameter(
 *         name="invite_code",
 *         in="query",
 *         description="Código de convite personalizado",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="temporary_password",
 *         in="query",
 *         description="Senha temporária enviada no convite",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Retorna indicação de job criado e payload usado",
 *         @OA\JsonContent(
 *             @OA\Property(property="job_created", type="boolean"),
 *             @OA\Property(property="reason", type="string", nullable=true, example="send_email parameter is not affirmative"),
 *             @OA\Property(property="user_id", type="string", format="uuid"),
 *             @OA\Property(property="recipient_email", type="string", format="email"),
 *             @OA\Property(
 *                 property="payload",
 *                 type="object",
 *                 @OA\Property(property="companyName", type="string", nullable=true),
 *                 @OA\Property(property="inviteUrl", type="string", nullable=true),
 *                 @OA\Property(property="inviteCode", type="string"),
 *                 @OA\Property(property="temporaryPassword", type="string"),
 *                 @OA\Property(property="supportEmail", type="string", format="email")
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Rota disponível apenas em ambientes locais ou de teste"
 *     )
 * )
 */
class SendEmployeeInviteJob {}
