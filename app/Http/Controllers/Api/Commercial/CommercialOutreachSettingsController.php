<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\CommercialOutreachSettingsUpdateRequest;
use App\Http\Resources\Commercial\CommercialOutreachSettingsResource;
use App\Models\CommercialOutreachSetting;
use App\Services\AuditLogService;

class CommercialOutreachSettingsController extends Controller
{
    public function __construct(protected AuditLogService $auditLogService) {}

    public function show()
    {
        return new CommercialOutreachSettingsResource(CommercialOutreachSetting::current());
    }

    public function update(CommercialOutreachSettingsUpdateRequest $request)
    {
        $settings = CommercialOutreachSetting::current();
        $data = $request->validated();
        $data['updated_by_user_id'] = $request->user()->id;

        if (($data['is_globally_paused'] ?? null) === true && ! $settings->is_globally_paused) {
            $data['paused_at'] = now();
            $data['paused_by_user_id'] = $request->user()->id;
        } elseif (($data['is_globally_paused'] ?? null) === false) {
            $data['paused_at'] = null;
            $data['paused_by_user_id'] = null;
            $data['pause_reason'] = null;
        }

        $settings->update($data);

        $this->auditLogService->log(
            action: 'commercial_email_settings.updated',
            entityType: CommercialOutreachSetting::class,
            entityId: $settings->id,
            description: 'Configurações de outreach comercial atualizadas',
        );

        return new CommercialOutreachSettingsResource($settings->fresh());
    }
}
