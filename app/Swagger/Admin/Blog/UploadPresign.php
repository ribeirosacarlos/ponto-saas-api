<?php

namespace App\Swagger\Admin\Blog;

/**
 * @OA\Post(
 *     path="/v1/admin/blog/uploads/presign",
 *     summary="Gera URL pré-assinada para upload de mídia do blog (S3)",
 *     description="Retorna uma URL pré-assinada (válida por 5 minutos) para upload direto ao S3 e a URL pública final do arquivo.",
 *     tags={"Admin - Blog"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *
 *         @OA\JsonContent(
 *             required={"filename","mime_type","folder"},
 *
 *             @OA\Property(property="filename", type="string", example="capa-ponto-espanha.jpg"),
 *             @OA\Property(property="mime_type", type="string", enum={"image/jpeg","image/png","image/webp"}),
 *             @OA\Property(property="folder", type="string", enum={"covers","heroes","og"})
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=200,
 *         description="URL pré-assinada gerada",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="upload_url", type="string", example="https://bucket.s3.amazonaws.com/blog/covers/abc.jpg?X-Amz-..."),
 *             @OA\Property(property="public_url", type="string", example="https://cdn.jornafy.com/blog/covers/abc.jpg")
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=422,
 *         description="Erro de validação",
 *
 *         @OA\JsonContent(
 *
 *             @OA\Property(property="message", type="string", example="The given data was invalid."),
 *             @OA\Property(property="errors", type="object")
 *         )
 *     )
 * )
 */
class UploadPresign {}
