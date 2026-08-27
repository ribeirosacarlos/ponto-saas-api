<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\CommercialEmailTemplateRequest;
use App\Http\Resources\Commercial\CommercialEmailTemplateResource;
use App\Models\CommercialEmailTemplate;
use App\Services\AuditLogService;

class CommercialEmailTemplateController extends Controller
{
    public function __construct(protected AuditLogService $auditLogService) {}

    public function index()
    {
        $templates = CommercialEmailTemplate::query()->orderByDesc('created_at')->get();

        return CommercialEmailTemplateResource::collection($templates);
    }

    public function store(CommercialEmailTemplateRequest $request)
    {
        $data = $request->validated();
        $data['created_by_user_id'] = $request->user()->id;

        $template = CommercialEmailTemplate::create($data);

        $this->auditLogService->log(
            action: 'commercial_email_template.created',
            entityType: CommercialEmailTemplate::class,
            entityId: $template->id,
            description: "Template de e-mail criado: {$template->name}",
        );

        return (new CommercialEmailTemplateResource($template))->response()->setStatusCode(201);
    }

    public function update(CommercialEmailTemplateRequest $request, string $id)
    {
        $template = CommercialEmailTemplate::findOrFail($id);
        $template->update($request->validated());

        $this->auditLogService->log(
            action: 'commercial_email_template.updated',
            entityType: CommercialEmailTemplate::class,
            entityId: $template->id,
            description: "Template de e-mail atualizado: {$template->name}",
        );

        return new CommercialEmailTemplateResource($template);
    }
}
