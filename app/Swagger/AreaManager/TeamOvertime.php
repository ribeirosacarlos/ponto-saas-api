<?php

namespace App\Swagger\AreaManager;

/**
 * ==========================================
 * Consulta de saldo de horas da equipe
 * ==========================================
 *
 * @OA\Get(
 *     path="/v1/area-manager/team/{employee}/overtime",
 *     summary="Resumo de horas extras/dívidas do membro da equipe",
 *     description="Permite que area managers vejam o saldo de horas da equipe (mesmo comportamento da rota admin).",
 *     tags={"Area Manager - Time Entries"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(
 *         name="employee",
 *         in="path",
 *         required=true,
 *         description="ID do funcionário",
 *         @OA\Schema(type="string", format="uuid")
 *     ),
 *
 *     @OA\Parameter(
 *         name="from",
 *         in="query",
 *         required=true,
 *         description="Data inicial (YYYY-MM-DD)",
 *         @OA\Schema(type="string", format="date", example="2026-01-01")
 *     ),
 *
 *     @OA\Parameter(
 *         name="to",
 *         in="query",
 *         required=true,
 *         description="Data final (YYYY-MM-DD)",
 *         @OA\Schema(type="string", format="date", example="2026-01-31")
 *     ),
 *
 *     @OA\Parameter(
 *         name="include_days",
 *         in="query",
 *         required=false,
 *         description="Use 1 para incluir o array diário em resposta.",
 *         @OA\Schema(type="integer", example=1, enum={0,1})
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="Resumo de horas extras/dívidas retornado com sucesso.",
 *         @OA\JsonContent(ref="#/components/schemas/OvertimeBalanceResponse")
 *     ),
 *
 *     @OA\Response(
 *         response=401,
 *         description="Usuário não autenticado."
 *     ),
 *
 *     @OA\Response(
 *         response=403,
 *         description="Usuário sem permissão para consultar o funcionário."
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Erro de validação"
 *     )
 * )
 */
class TeamOvertime {}
