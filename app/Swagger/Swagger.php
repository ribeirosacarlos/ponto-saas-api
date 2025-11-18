<?php

namespace App\Swagger;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         version="1.0.0",
 *         title="Ponto SaaS API",
 *         description="Documentação da API de controle de ponto"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/v1",
 *     description="Local"
 * )
 *
 * @OA\Server(
 *     url="https://api.suaempresa.com/v1",
 *     description="Produção"
 * )
 */
class Swagger {}
