<?php

namespace App\Swagger\Commercial;

/**
 * @OA\Tag(
 *     name="Commercial - Leads",
 *     description="Gestão de leads do módulo Comercial. Acesso restrito a super_admin."
 * )
 */
class Leads {}

/**
 * @OA\Get(
 *     path="/v1/admin/commercial/leads",
 *     summary="Lista leads comerciais",
 *     description="super_admin vê todos os leads.",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"new","in_progress","demo_scheduled","proposal_sent","won","lost","nurturing"})),
 *     @OA\Parameter(name="priority", in="query", @OA\Schema(type="string", enum={"low","medium","high","very_high"})),
 *     @OA\Parameter(name="current_step_id", in="query", @OA\Schema(type="string", format="uuid")),
 *     @OA\Parameter(name="is_overdue", in="query", description="Filtra leads cuja etapa atual já venceu (true) ou ainda está no prazo (false), com base em current_step_started_at + default_due_days da etapa", @OA\Schema(type="boolean")),
 *     @OA\Parameter(name="assigned_to_user_id", in="query", @OA\Schema(type="string", format="uuid")),
 *     @OA\Parameter(name="affiliate_id", in="query", @OA\Schema(type="string", format="uuid")),
 *     @OA\Parameter(name="country", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="city", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="segment", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="source", in="query", @OA\Schema(type="string")),
 *     @OA\Parameter(name="next_action_from", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="next_action_to", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="created_from", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="created_to", in="query", @OA\Schema(type="string", format="date")),
 *     @OA\Parameter(name="search", in="query", description="Busca em company_name, contact_name, email, phone, whatsapp, website", @OA\Schema(type="string")),
 *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
 *
 *     @OA\Response(response=200, description="Lista paginada de leads. Cada lead inclui created_by_user {id,name,email} (quem cadastrou o lead) e assigned_to_user {id,name,email} (responsável atual), além dos campos calculados de pipeline (current_stage_name, current_stage_due_at, current_stage_is_overdue, current_stage_warning_message, next_stage_id, next_stage_name)."),
 *     @OA\Response(response=401, description="Não autenticado"),
 *     @OA\Response(response=403, description="Role sem acesso ao módulo Comercial")
 * )
 */
class LeadsIndex {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/leads",
 *     summary="Cria um novo lead",
 *     description="Disponível para super_admin. Bloqueia (422) se já existir lead com mesmo email, telefone (normalizado, ignorando formatação) ou google_maps_place_id. Também sinaliza (sem bloquear) possíveis duplicados por whatsapp, website ou nome da empresa.",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *
 *         @OA\JsonContent(
 *             required={"company_name"},
 *
 *             @OA\Property(property="company_name", type="string", example="Acme Ltda"),
 *             @OA\Property(property="contact_name", type="string", nullable=true),
 *             @OA\Property(property="email", type="string", format="email", nullable=true),
 *             @OA\Property(property="phone", type="string", nullable=true),
 *             @OA\Property(property="whatsapp", type="string", nullable=true),
 *             @OA\Property(property="website", type="string", nullable=true),
 *             @OA\Property(property="google_maps_place_id", type="string", nullable=true, description="place_id do Google Maps (identificador estável do local)"),
 *             @OA\Property(property="country", type="string", nullable=true),
 *             @OA\Property(property="city", type="string", nullable=true),
 *             @OA\Property(property="segment", type="string", nullable=true),
 *             @OA\Property(property="employees_count", type="integer", nullable=true),
 *             @OA\Property(property="source", type="string", nullable=true),
 *             @OA\Property(property="affiliate_id", type="string", format="uuid", nullable=true),
 *             @OA\Property(property="current_step_id", type="string", format="uuid", nullable=true),
 *             @OA\Property(property="assigned_to_user_id", type="string", format="uuid", nullable=true, description="Precisa ser um usuário com role super_admin"),
 *             @OA\Property(property="priority", type="string", enum={"low","medium","high","very_high"}, default="medium"),
 *             @OA\Property(property="general_notes", type="string", nullable=true)
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=201,
 *         description="Lead criado. Inclui aviso de possíveis duplicados fracos (duplicate_warning, possible_duplicates) por whatsapp/website/nome da empresa.",
 *     ),
 *     @OA\Response(response=422, description="Erro de validação ou duplicidade bloqueante (email, phone ou google_maps_place_id já cadastrados)")
 * )
 */
class LeadsStore {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/leads/bulk",
 *     summary="Cria múltiplos leads de uma vez",
 *     description="Disponível para super_admin, commercial_manager e commercial_agent. Aceita até 100 leads por requisição. Cada item é processado de forma independente (sucesso parcial): itens inválidos ou duplicados (mesmo email, telefone normalizado ou google_maps_place_id) não impedem a criação dos demais. A resposta traz o resultado individual de cada item, na mesma ordem enviada.",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\RequestBody(
 *         required=true,
 *
 *         @OA\JsonContent(
 *             required={"leads"},
 *
 *             @OA\Property(
 *                 property="leads",
 *                 type="array",
 *                 minItems=1,
 *                 maxItems=100,
 *
 *                 @OA\Items(
 *                     required={"company_name"},
 *
 *                     @OA\Property(property="company_name", type="string", example="Acme Ltda"),
 *                     @OA\Property(property="contact_name", type="string", nullable=true),
 *                     @OA\Property(property="email", type="string", format="email", nullable=true),
 *                     @OA\Property(property="phone", type="string", nullable=true),
 *                     @OA\Property(property="whatsapp", type="string", nullable=true),
 *                     @OA\Property(property="website", type="string", nullable=true),
 *                     @OA\Property(property="google_maps_place_id", type="string", nullable=true),
 *                     @OA\Property(property="country", type="string", nullable=true),
 *                     @OA\Property(property="city", type="string", nullable=true),
 *                     @OA\Property(property="segment", type="string", nullable=true),
 *                     @OA\Property(property="employees_count", type="integer", nullable=true),
 *                     @OA\Property(property="source", type="string", nullable=true),
 *                     @OA\Property(property="affiliate_id", type="string", format="uuid", nullable=true),
 *                     @OA\Property(property="current_step_id", type="string", format="uuid", nullable=true),
 *                     @OA\Property(property="assigned_to_user_id", type="string", format="uuid", nullable=true, description="Precisa ser um usuário com role super_admin"),
 *                     @OA\Property(property="priority", type="string", enum={"low","medium","high","very_high"}, default="medium"),
 *                     @OA\Property(property="general_notes", type="string", nullable=true)
 *                 )
 *             )
 *         )
 *     ),
 *
 *     @OA\Response(
 *         response=207,
 *         description="Processamento concluído (sucesso parcial). data[] traz, por índice (mesma posição do array enviado): status ('created' ou 'error'), e para itens criados o lead serializado + duplicate_warning/possible_duplicates, ou para itens com erro o objeto errors (mesmo formato de erro de validação do Laravel). meta traz total, created e failed.",
 *     ),
 *     @OA\Response(response=422, description="Erro de validação no payload geral (ex: leads ausente, vazio ou acima do limite de 100 itens)")
 * )
 */
class LeadsBulkStore {}

/**
 * @OA\Get(
 *     path="/v1/admin/commercial/leads/{id}",
 *     summary="Exibe um lead",
 *     description="Disponível para super_admin.",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Detalhes do lead, incluindo notas e histórico de etapas"),
 *     @OA\Response(response=404, description="Lead não encontrado ou fora do escopo do usuário")
 * )
 */
class LeadsShow {}

/**
 * @OA\Put(
 *     path="/v1/admin/commercial/leads/{id}",
 *     summary="Atualiza um lead",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Lead atualizado"),
 *     @OA\Response(response=403, description="Sem permissão para editar o lead"),
 *     @OA\Response(response=422, description="Erro de validação")
 * )
 */
class LeadsUpdate {}

/**
 * @OA\Delete(
 *     path="/v1/admin/commercial/leads/{id}",
 *     summary="Remove um lead (soft delete)",
 *     description="Restrito a super_admin.",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\Response(response=200, description="Lead removido"),
 *     @OA\Response(response=403, description="Sem permissão para remover leads")
 * )
 */
class LeadsDestroy {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/leads/{id}/assign",
 *     summary="Atribui o lead a um usuário comercial",
 *     description="Restrito a super_admin.",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"assigned_to_user_id"}, @OA\Property(property="assigned_to_user_id", type="string", format="uuid"))),
 *
 *     @OA\Response(response=200, description="Responsável atualizado"),
 *     @OA\Response(response=403, description="Sem permissão para atribuir leads")
 * )
 */
class LeadsAssign {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/leads/{id}/move-step",
 *     summary="Move o lead para outra etapa comercial",
 *     description="Disponível para super_admin. Cria um registro em lead_step_logs.",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"step_id"},
 *
 *         @OA\Property(property="step_id", type="string", format="uuid"),
 *         @OA\Property(property="note", type="string", nullable=true),
 *         @OA\Property(property="scheduled_at", type="string", format="date-time", nullable=true)
 *     )),
 *
 *     @OA\Response(response=200, description="Etapa atualizada")
 * )
 */
class LeadsMoveStep {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/leads/{id}/notes",
 *     summary="Adiciona uma observação ao lead",
 *     description="Disponível para super_admin.",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"note"}, @OA\Property(property="note", type="string"))),
 *
 *     @OA\Response(response=201, description="Nota criada")
 * )
 */
class LeadsAddNote {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/leads/{id}/next-action",
 *     summary="Define a próxima ação do lead",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\RequestBody(required=true, @OA\JsonContent(required={"next_action_type","next_action_at"},
 *
 *         @OA\Property(property="next_action_type", type="string"),
 *         @OA\Property(property="next_action_at", type="string", format="date-time"),
 *         @OA\Property(property="next_action_user_id", type="string", format="uuid", nullable=true)
 *     )),
 *
 *     @OA\Response(response=200, description="Próxima ação registrada")
 * )
 */
class LeadsNextAction {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/leads/{id}/mark-won",
 *     summary="Marca o lead como convertido (cliente ganho)",
 *     description="Se o lead tiver afiliado e base_amount for informado, gera as comissões recorrentes (padrão: 20% por 6 meses).",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\RequestBody(@OA\JsonContent(
 *
 *         @OA\Property(property="customer_id", type="string", format="uuid", nullable=true, description="ID da empresa (companies) criada para este cliente"),
 *         @OA\Property(property="base_amount", type="number", format="float", nullable=true, description="Valor mensal base usado para calcular a comissão do afiliado")
 *     )),
 *
 *     @OA\Response(response=200, description="Lead convertido")
 * )
 */
class LeadsMarkWon {}

/**
 * @OA\Post(
 *     path="/v1/admin/commercial/leads/{id}/mark-lost",
 *     summary="Marca o lead como perdido",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
 *
 *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="lost_reason", type="string", nullable=true))),
 *
 *     @OA\Response(response=200, description="Lead marcado como perdido")
 * )
 */
class LeadsMarkLost {}

/**
 * @OA\Get(
 *     path="/v1/admin/commercial/dashboard",
 *     summary="Métricas comerciais (dashboard)",
 *     description="Métricas completas para super_admin.",
 *     tags={"Commercial - Leads"},
 *     security={{"bearerAuth":{}}},
 *
 *     @OA\Response(
 *         response=200,
 *         description="Métricas agregadas: total_leads, new_leads, leads_in_progress, demos_scheduled, proposals_sent, won_leads, lost_leads, leads_by_step, leads_by_priority, leads_by_agent, leads_by_affiliate, next_actions_today, overdue_next_actions"
 *     )
 * )
 */
class Dashboard {}
