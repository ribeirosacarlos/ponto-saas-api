<?php

namespace App\Http\Controllers\Api\Commercial;

use App\Actions\Commercial\InviteAffiliateAction;
use App\Actions\Commercial\InviteAffiliateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\CommercialAffiliateRequest;
use App\Http\Resources\Commercial\CommercialAffiliateResource;
use App\Models\CommercialAffiliate;
use App\Services\AuditLogService;
use App\Services\Commercial\CommercialAffiliateMetricsService;
use Illuminate\Http\Request;

class CommercialAffiliateController extends Controller
{
    public function __construct(protected AuditLogService $auditLogService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', CommercialAffiliate::class);

        $affiliates = CommercialAffiliate::query()
            ->with('commissionPlan')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 20));

        return CommercialAffiliateResource::collection($affiliates);
    }

    public function store(CommercialAffiliateRequest $request, InviteAffiliateAction $inviteAction, InviteAffiliateUserAction $userInviteAction)
    {
        $this->authorize('create', CommercialAffiliate::class);

        $data = $request->validated();
        $createAccount = (bool) ($data['create_account'] ?? false);

        unset($data['create_account'], $data['password'], $data['password_confirmation']);

        $affiliate = $inviteAction->execute($data, sendEmail: ! $createAccount);

        if ($createAccount) {
            $userInviteAction->execute($affiliate);
        }

        return (new CommercialAffiliateResource($affiliate->load('user')))->response()->setStatusCode(201);
    }

    public function resendInvite(string $id, InviteAffiliateAction $action)
    {
        $this->authorize('update', CommercialAffiliate::class);

        $affiliate = CommercialAffiliate::findOrFail($id);
        $action->resend($affiliate);

        return response()->json(['message' => 'Convite reenviado com sucesso.']);
    }

    public function show(string $id)
    {
        $this->authorize('view', CommercialAffiliate::class);

        $affiliate = CommercialAffiliate::with('commissionPlan')->findOrFail($id);

        return new CommercialAffiliateResource($affiliate);
    }

    public function update(CommercialAffiliateRequest $request, string $id)
    {
        $this->authorize('update', CommercialAffiliate::class);

        $affiliate = CommercialAffiliate::findOrFail($id);
        $affiliate->update($request->validated());

        $this->auditLogService->log(
            action: 'affiliate.updated',
            entityType: CommercialAffiliate::class,
            entityId: $affiliate->id,
            description: "Afiliado atualizado: {$affiliate->name}",
        );

        return new CommercialAffiliateResource($affiliate);
    }

    public function destroy(string $id)
    {
        $this->authorize('delete', CommercialAffiliate::class);

        $affiliate = CommercialAffiliate::findOrFail($id);
        $affiliate->delete();

        $this->auditLogService->log(
            action: 'affiliate.deleted',
            entityType: CommercialAffiliate::class,
            entityId: $affiliate->id,
            description: "Afiliado removido: {$affiliate->name}",
        );

        return response()->json(['message' => 'Afiliado removido com sucesso.']);
    }

    public function metrics(string $id, CommercialAffiliateMetricsService $metricsService)
    {
        $this->authorize('view', CommercialAffiliate::class);

        $affiliate = CommercialAffiliate::findOrFail($id);

        return response()->json($metricsService->build($affiliate));
    }
}
