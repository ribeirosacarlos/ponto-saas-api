<?php

namespace App\Swagger;

/**
 * @OA\Tag(name="Timesheets", description="Folhas de ponto mensais e assinaturas")
 *
 * @OA\Schema(
 *     schema="TimesheetResource",
 *     type="object",
 *     description="Folha de ponto segura. O caminho interno do PDF não é exposto.",
 *
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="closure_id", type="string", format="uuid"),
 *     @OA\Property(property="employee", type="object", nullable=true,
 *         @OA\Property(property="id", type="string", format="uuid"),
 *         @OA\Property(property="name", type="string")
 *     ),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="snapshot", type="object"),
 *     @OA\Property(property="snapshot_generated_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="signatures", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="open_dispute", type="object", nullable=true),
 *     @OA\Property(property="pdf_available", type="boolean", description="Use o endpoint /pdf quando true."),
 *     @OA\Property(property="pdf_generated_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="document_hash", type="string", nullable=true, description="SHA-256 para verificação de integridade."),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Get(
 *     path="/v1/employee/timesheets",
 *     tags={"Timesheets"}, security={{"bearerAuth":{}}}, summary="Lista as próprias folhas",
 *
 *     @OA\Response(response=200, description="Lista paginada", @OA\JsonContent(
 *
 *         @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TimesheetResource"))
 *     ))
 * )
 *
 * @OA\Get(
 *     path="/v1/employee/timesheets/{timesheet}",
 *     tags={"Timesheets"}, security={{"bearerAuth":{}}}, summary="Detalha a própria folha",
 *
 *     @OA\Parameter(name="timesheet", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Folha", @OA\JsonContent(ref="#/components/schemas/TimesheetResource")),
 *     @OA\Response(response=403, description="Folha fora do escopo"),
 *     @OA\Response(response=404, description="Folha não encontrada")
 * )
 *
 * @OA\Get(
 *     path="/v1/employee/timesheets/{timesheet}/pdf",
 *     tags={"Timesheets"}, security={{"bearerAuth":{}}}, summary="Baixa ou obtém URL temporária do PDF",
 *
 *     @OA\Parameter(name="timesheet", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Arquivo ou objeto com URL temporária"),
 *     @OA\Response(response=404, description="PDF indisponível")
 * )
 *
 * @OA\Get(
 *     path="/v1/admin/timesheets/{timesheet}",
 *     tags={"Timesheets"}, security={{"bearerAuth":{}}}, summary="Detalha folha visível ao gestor",
 *
 *     @OA\Parameter(name="timesheet", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Folha", @OA\JsonContent(ref="#/components/schemas/TimesheetResource")),
 *     @OA\Response(response=403, description="Empresa ou área fora do escopo")
 * )
 */
class Timesheets {}
