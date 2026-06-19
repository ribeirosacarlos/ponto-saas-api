<?php

namespace App\Swagger\Public\Blog;

/**
 * @OA\Get(
 *     path="/v1/public/blog/categories",
 *     summary="Lista categorias do blog",
 *     description="Endpoint público, sem autenticação.",
 *     tags={"Public - Blog"},
 *
 *     @OA\Response(
 *         response=200,
 *         description="OK",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *
 *                 @OA\Items(
 *
 *                     @OA\Property(property="key", type="string", example="compliance"),
 *                     @OA\Property(property="label_pt", type="string"),
 *                     @OA\Property(property="label_es", type="string"),
 *                     @OA\Property(property="label_en", type="string")
 *                 )
 *             )
 *         )
 *     )
 * )
 */
class Categories {}
