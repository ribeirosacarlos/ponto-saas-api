<?php

namespace App\Swagger;

/**
 * @OA\OpenApi(
 *
 *     @OA\Info(
 *         version="1.1.0",
 *         title="Ponto SaaS API",
 *         description="Documentação da API de controle de ponto"
 *     ),
 *
 *     @OA\Server(
 *         url="http://localhost:8000/api",
 *         description="Local"
 *     ),
 *     @OA\Server(
 *         url="https://api.jornafy.com/api",
 *         description="Produção"
 *     ),
 *
 *     @OA\Components(
 *
 *         @OA\SecurityScheme(
 *             securityScheme="bearerAuth",
 *             type="http",
 *             scheme="bearer",
 *             bearerFormat="Sanctum"
 *         )
 *     )
 * )
 */
class Swagger {}
