<?php

namespace App\Swagger\Settings;

/**
 * @OA\Get(
 *     path="/v1/settings/overview",
 *     summary="Retorna o overview das configurações e billing da empresa autenticada",
 *     description="Fornece um snapshot local de billing, flags e metadados de configurações. Dados Stripe são mantidos em cache na tabela `subscriptions` (sincronizados por webhooks); quando estiverem ausentes retornam `null` mas a estrutura permanece.",
 *     tags={"Settings"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(
 *         response=200,
 *         description="Overview completo preparado para a UI",
 *         @OA\JsonContent(ref="#/components/schemas/SettingsOverviewResponse")
 *     ),
 *     @OA\Response(response=401, description="Requisição não autenticada"),
 *     @OA\Response(response=404, description="Empresa não encontrada para o usuário autenticado")
 * )
 */
class CompanySettingsOverview {}
