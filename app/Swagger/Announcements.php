<?php

namespace App\Swagger;

/**
 * @OA\Tag(
 *     name="Employee - Announcements",
 *     description="Comunicados internos disponíveis para o colaborador"
 * )
 *
 * @OA\Tag(
 *     name="Admin - Announcements",
 *     description="Administração dos comunicados internos"
 * )
 *
 * @OA\Schema(
 *     schema="AnnouncementResource",
 *     type="object",
 *     @OA\Property(property="id", type="string", format="uuid"),
 *     @OA\Property(property="title", type="string", example="Feriado prolongado confirmado"),
 *     @OA\Property(property="summary", type="string", nullable=true, example="Expediente suspenso na sexta"),
 *     @OA\Property(property="body", type="string", nullable=true, example="O escritório ficará fechado para manutenção."),
 *     @OA\Property(property="sentAt", type="string", format="date-time", example="2026-01-05T12:00:00Z"),
 *     @OA\Property(property="seenAt", type="string", format="date-time", nullable=true, example="2026-01-05T13:00:00Z"),
 *     @OA\Property(property="status", type="string", example="pending"),
 *     @OA\Property(property="senderName", type="string", example="Ana Pereira"),
 *     @OA\Property(property="senderRole", type="string", example="manager"),
 *     @OA\Property(property="type", type="string", nullable=true, example="holiday")
 * )
 *
 * @OA\Schema(
 *     schema="AnnouncementSeenResponse",
 *     type="object",
 *     @OA\Property(property="seenAt", type="string", format="date-time", example="2026-01-05T13:00:00Z"),
 *     @OA\Property(property="status", type="string", example="seen")
 * )
 *
 * @OA\Schema(
 *     schema="AnnouncementPendingCount",
 *     type="object",
 *     @OA\Property(property="count", type="integer", example=3)
 * )
 */
class Announcements {}


/**
 * @OA\Get(
 *     path="/v1/employee/announcements",
 *     summary="Lista comunicados visíveis para o colaborador",
 *     tags={"Employee - Announcements"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="from", in="query", description="Data mínima de envio (YYYY-MM-DD)", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", description="Data máxima de envio (YYYY-MM-DD)", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="status", in="query", description="Status do comunicado", required=false, @OA\Schema(type="string", enum={"pending","seen"})),
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada de comunicados",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=20),
 *             @OA\Property(property="last_page", type="integer", example=5),
 *             @OA\Property(property="total", type="integer", example=100),
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AnnouncementResource"))
 *         )
 *     )
 * )
 */
class EmployeeAnnouncementsIndex {}


/**
 * @OA\Get(
 *     path="/v1/employee/announcements/{announcement}",
 *     summary="Consulta um comunicado específico",
 *     tags={"Employee - Announcements"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="announcement", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Comunicado retornado", @OA\JsonContent(ref="#/components/schemas/AnnouncementResource"))
 * )
 */
class EmployeeAnnouncementsShow {}


/**
 * @OA\Post(
 *     path="/v1/employee/announcements/{announcement}/seen",
 *     summary="Marca um comunicado como lido",
 *     tags={"Employee - Announcements"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="announcement", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Status atualizado", @OA\JsonContent(ref="#/components/schemas/AnnouncementSeenResponse"))
 * )
 */
class EmployeeAnnouncementsSeen {}


/**
 * @OA\Get(
 *     path="/v1/employee/announcements/pending-count",
 *     summary="Conta comunicados pendentes do colaborador",
 *     tags={"Employee - Announcements"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Response(response=200, description="Quantidade pendente", @OA\JsonContent(ref="#/components/schemas/AnnouncementPendingCount"))
 * )
 */
class EmployeeAnnouncementsPendingCount {}


/**
 * @OA\Get(
 *     path="/v1/admin/announcements",
 *     summary="Lista comunicados da empresa (admin)",
 *     tags={"Admin - Announcements"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="from", in="query", description="Data mínima de envio (YYYY-MM-DD)", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="to", in="query", description="Data máxima de envio (YYYY-MM-DD)", required=false, @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="type", in="query", description="Tipo de comunicado", required=false, @OA\Schema(type="string", enum={"general","holiday","vacation","tip"})),
 *     @OA\Parameter(name="query", in="query", description="Busca por título", required=false, @OA\Schema(type="string")),
 *     @OA\Response(
 *         response=200,
 *         description="Lista paginada",
 *         @OA\JsonContent(
 *             @OA\Property(property="current_page", type="integer", example=1),
 *             @OA\Property(property="per_page", type="integer", example=20),
 *             @OA\Property(property="last_page", type="integer", example=5),
 *             @OA\Property(property="total", type="integer", example=100),
 *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AnnouncementResource"))
 *         )
 *     )
 * )
 */
class AdminAnnouncementsIndex {}


/**
 * @OA\Post(
 *     path="/v1/admin/announcements",
 *     summary="Cria um novo comunicado",
 *     tags={"Admin - Announcements"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"title"},
 *             @OA\Property(property="title", type="string", example="Novas orientações para o expediente"),
 *             @OA\Property(property="summary", type="string", nullable=true),
 *             @OA\Property(property="body", type="string", nullable=true),
 *             @OA\Property(property="type", type="string", nullable=true, enum={"general","holiday","vacation","tip"}),
 *             @OA\Property(property="sent_at", type="string", format="date-time", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=201, description="Comunicado criado", @OA\JsonContent(ref="#/components/schemas/AnnouncementResource"))
 * )
 */
class AdminAnnouncementsStore {}


/**
 * @OA\Get(
 *     path="/v1/admin/announcements/{announcement}",
 *     summary="Recupera os detalhes de um comunicado (admin)",
 *     tags={"Admin - Announcements"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="announcement", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Comunicado retornado", @OA\JsonContent(ref="#/components/schemas/AnnouncementResource"))
 * )
 */
class AdminAnnouncementsShow {}


/**
 * @OA\Put(
 *     path="/v1/admin/announcements/{announcement}",
 *     summary="Atualiza um comunicado",
 *     tags={"Admin - Announcements"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="announcement", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="title", type="string"),
 *             @OA\Property(property="summary", type="string", nullable=true),
 *             @OA\Property(property="body", type="string", nullable=true),
 *             @OA\Property(property="type", type="string", nullable=true, enum={"general","holiday","vacation","tip"}),
 *             @OA\Property(property="sent_at", type="string", format="date-time", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=200, description="Comunicado atualizado", @OA\JsonContent(ref="#/components/schemas/AnnouncementResource"))
 * )
 */
class AdminAnnouncementsUpdate {}


/**
 * @OA\Patch(
 *     path="/v1/admin/announcements/{announcement}",
 *     summary="Atualiza parcialmente um comunicado",
 *     tags={"Admin - Announcements"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="announcement", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\RequestBody(
 *         required=false,
 *         @OA\JsonContent(
 *             @OA\Property(property="title", type="string"),
 *             @OA\Property(property="summary", type="string", nullable=true),
 *             @OA\Property(property="body", type="string", nullable=true),
 *             @OA\Property(property="type", type="string", nullable=true, enum={"general","holiday","vacation","tip"}),
 *             @OA\Property(property="sent_at", type="string", format="date-time", nullable=true)
 *         )
 *     ),
 *     @OA\Response(response=200, description="Comunicado atualizado parcialmente", @OA\JsonContent(ref="#/components/schemas/AnnouncementResource"))
 * )
 */
class AdminAnnouncementsPartialUpdate {}


/**
 * @OA\Delete(
 *     path="/v1/admin/announcements/{announcement}",
 *     summary="Remove um comunicado",
 *     tags={"Admin - Announcements"},
 *     security={{"bearerAuth":{}}},
 *     @OA\Parameter(name="announcement", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *     @OA\Response(response=200, description="Comunicado removido", @OA\JsonContent(@OA\Property(property="message", type="string", example="Comunicado removido.")))
 * )
 */
class AdminAnnouncementsDestroy {}
