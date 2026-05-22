<?php

namespace App\Services\Timesheet;

use App\Enums\TimesheetSignatureRole;
use App\Enums\TimesheetStatus;
use App\Models\EmployeeTimesheet;
use App\Models\TimesheetSignature;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\UserVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TimesheetSignatureService
{
    public function __construct(
        protected UserVisibilityService $userVisibilityService,
        protected MonthlyClosureService $closureService,
        protected AuditLogService $auditLogService,
        protected TimesheetPdfService $pdfService
    ) {}

    public function signAsEmployee(EmployeeTimesheet $timesheet, User $signer, Request $request): TimesheetSignature
    {
        if ((string) $signer->id !== (string) $timesheet->employee_id) {
            abort(403, 'Você só pode assinar a própria folha.');
        }

        if ($timesheet->status !== TimesheetStatus::PENDING_EMPLOYEE) {
            throw ValidationException::withMessages([
                'status' => 'A folha não está aguardando assinatura do colaborador.',
            ]);
        }

        if ($timesheet->hasOpenDispute()) {
            throw ValidationException::withMessages([
                'dispute' => 'Existe uma contestação em aberto. Resolva antes de assinar.',
            ]);
        }

        $this->validatePassword($signer, $request->string('password')->toString());

        $signatureImagePath = $this->saveSignatureImage(
            $request->string('signature_image')->toString(),
            $timesheet
        );

        $documentHash = $timesheet->computeDocumentHash();
        $signatureHash = hash('sha256', $signatureImagePath . $documentHash . $signer->id . now()->toIso8601String());

        $signature = DB::transaction(function () use ($timesheet, $signer, $request, $signatureImagePath, $documentHash, $signatureHash) {
            $signature = TimesheetSignature::create([
                'company_id' => $timesheet->company_id,
                'employee_timesheet_id' => $timesheet->id,
                'signer_id' => $signer->id,
                'role' => TimesheetSignatureRole::EMPLOYEE->value,
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'signature_image_path' => $signatureImagePath,
                'document_hash' => $documentHash,
                'signature_hash' => $signatureHash,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'accepted_terms' => true,
                'password_confirmed_at' => now(),
                'metadata' => [
                    'device_type' => str_contains(strtolower($request->userAgent() ?? ''), 'mobile') ? 'mobile' : 'desktop',
                ],
            ]);

            $timesheet->update([
                'status' => TimesheetStatus::PENDING_MANAGER->value,
                'document_hash' => $documentHash,
            ]);

            return $signature;
        });

        $this->auditLogService->log(
            action: 'timesheet.signed_by_employee',
            entityType: EmployeeTimesheet::class,
            entityId: $timesheet->id,
            description: 'Folha assinada pelo colaborador',
            companyId: $timesheet->company_id,
        );

        return $signature;
    }

    public function signAsManager(EmployeeTimesheet $timesheet, User $signer, Request $request): TimesheetSignature
    {
        $employee = $timesheet->employee;

        if (! $this->userVisibilityService->canManageUser($signer, $employee)) {
            abort(403, 'Você não tem permissão para assinar a folha deste colaborador.');
        }

        if ($timesheet->status !== TimesheetStatus::PENDING_MANAGER) {
            throw ValidationException::withMessages([
                'status' => 'A folha não está aguardando assinatura do gestor.',
            ]);
        }

        $signature = DB::transaction(function () use ($timesheet, $signer, $request) {
            $signature = TimesheetSignature::create([
                'company_id' => $timesheet->company_id,
                'employee_timesheet_id' => $timesheet->id,
                'signer_id' => $signer->id,
                'role' => TimesheetSignatureRole::MANAGER->value,
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'document_hash' => $timesheet->document_hash,
                'accepted_terms' => true,
            ]);

            $timesheet->update(['status' => TimesheetStatus::COMPLETED->value]);

            $this->closureService->checkAndAdvanceToClosed($timesheet->monthlyClosure);

            return $signature;
        });

        $timesheet->refresh();
        $this->pdfService->generateSignedPdf($timesheet);

        $this->auditLogService->log(
            action: 'timesheet.signed_by_manager',
            entityType: EmployeeTimesheet::class,
            entityId: $timesheet->id,
            description: 'Folha assinada pelo gestor',
            companyId: $timesheet->company_id,
        );

        return $signature;
    }

    /**
     * Invalida assinaturas ativas quando o snapshot é regerado após uma assinatura existir.
     * Retorna true se havia assinaturas ativas (e o status foi redefinido).
     */
    public function supersedePreviousSignatures(EmployeeTimesheet $timesheet): bool
    {
        $activeSignatures = $timesheet->activeSignatures()->get();

        if ($activeSignatures->isEmpty()) {
            return false;
        }

        DB::transaction(function () use ($timesheet, $activeSignatures) {
            $activeSignatures->each(fn ($sig) => $sig->update(['superseded_at' => now()]));

            $timesheet->update([
                'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
                'pdf_path' => null,
                'pdf_generated_at' => null,
                'document_hash' => null,
            ]);
        });

        $this->auditLogService->log(
            action: 'timesheet.signatures_superseded',
            entityType: EmployeeTimesheet::class,
            entityId: $timesheet->id,
            description: 'Assinaturas anteriores invalidadas por alteração no documento',
            companyId: $timesheet->company_id,
        );

        return true;
    }

    private function validatePassword(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Senha incorreta. Confirme sua senha para assinar.',
            ]);
        }
    }

    private function saveSignatureImage(string $base64Image, EmployeeTimesheet $timesheet): string
    {
        $base64Image = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);

        if (strlen($base64Image) > 1_000_000) {
            throw ValidationException::withMessages([
                'signature_image' => 'A imagem da assinatura é muito grande.',
            ]);
        }

        $imageData = base64_decode($base64Image, strict: true);

        if ($imageData === false) {
            throw ValidationException::withMessages([
                'signature_image' => 'Formato de assinatura inválido.',
            ]);
        }

        $path = sprintf(
            'timesheets/signatures/%s/%s_%s.png',
            $timesheet->company_id,
            $timesheet->id,
            now()->format('YmdHis')
        );

        Storage::disk(config('filesystems.default', 'local'))->put($path, $imageData);

        return $path;
    }
}
