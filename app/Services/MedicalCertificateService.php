<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\Document;
use App\Models\MonthlyClosure;
use App\Models\User;
use App\Models\VacationDay;
use App\Services\TimeEntry\AbsenceTimeEntryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MedicalCertificateService
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected AbsenceTimeEntryService $absenceTimeEntryService
    ) {}

    public function createFromEmployee(User $employee, array $payload, array $files = []): Absence
    {
        return $this->create($employee, $employee, $payload, $files, Absence::STATUS_PENDING);
    }

    public function createFromAdmin(User $actor, User $employee, array $payload, array $files = []): Absence
    {
        return $this->create($actor, $employee, $payload, $files, Absence::STATUS_APPROVED);
    }

    public function approve(Absence $absence, User $actor): Absence
    {
        $this->assertMedicalCertificate($absence);
        $this->assertPending($absence);
        $this->assertNoClosedMonthlyClosure($absence->company_id, $absence->start_date->toDateString(), $absence->end_date?->toDateString() ?? $absence->start_date->toDateString());
        $this->assertNoOverlappingAbsence($absence->company_id, $absence->user_id, $absence->start_date->toDateString(), $absence->end_date?->toDateString() ?? $absence->start_date->toDateString(), $absence->id);
        $this->assertNoOverlappingVacation($absence->company_id, $absence->user_id, $absence->start_date->toDateString(), $absence->end_date?->toDateString() ?? $absence->start_date->toDateString());

        return DB::transaction(function () use ($absence, $actor) {
            $oldValues = $this->auditLogService->snapshot($absence);

            $absence->update([
                'status' => Absence::STATUS_APPROVED,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);

            $this->absenceTimeEntryService->syncForAbsence($absence);

            $this->auditLogService->log(
                action: 'medical_certificate.approved',
                entityType: Absence::class,
                entityId: $absence->id,
                description: 'Atestado médico aprovado',
                oldValues: $oldValues,
                newValues: $this->auditLogService->snapshot($absence->fresh()),
                companyId: $absence->company_id,
            );

            return $absence->fresh(['documents', 'user']);
        });
    }

    public function reject(Absence $absence, User $actor, string $reason): Absence
    {
        $this->assertMedicalCertificate($absence);
        $this->assertPending($absence);

        $oldValues = $this->auditLogService->snapshot($absence);

        $absence->update([
            'status' => Absence::STATUS_REJECTED,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->auditLogService->log(
            action: 'medical_certificate.rejected',
            entityType: Absence::class,
            entityId: $absence->id,
            description: 'Atestado médico rejeitado',
            oldValues: $oldValues,
            newValues: $this->auditLogService->snapshot($absence->fresh()),
            companyId: $absence->company_id,
        );

        return $absence->fresh(['documents', 'user']);
    }

    public function cancel(Absence $absence, User $actor): Absence
    {
        $this->assertMedicalCertificate($absence);
        $this->assertPending($absence);
        $this->assertNoClosedMonthlyClosure($absence->company_id, $absence->start_date->toDateString(), $absence->end_date?->toDateString() ?? $absence->start_date->toDateString());

        $oldValues = $this->auditLogService->snapshot($absence);

        $absence->update([
            'status' => Absence::STATUS_CANCELED,
            'canceled_by' => $actor->id,
            'canceled_at' => now(),
        ]);

        $this->auditLogService->log(
            action: 'medical_certificate.canceled',
            entityType: Absence::class,
            entityId: $absence->id,
            description: 'Atestado médico cancelado',
            oldValues: $oldValues,
            newValues: $this->auditLogService->snapshot($absence->fresh()),
            companyId: $absence->company_id,
        );

        return $absence->fresh(['documents', 'user']);
    }

    private function create(User $actor, User $employee, array $payload, array $files, string $status): Absence
    {
        [$startDate, $endDate, $startTime, $endTime] = $this->normalizeCoverage($payload);

        $this->assertNoClosedMonthlyClosure($employee->company_id, $startDate, $endDate);
        $this->assertNoOverlappingAbsence($employee->company_id, $employee->id, $startDate, $endDate);
        $this->assertNoOverlappingVacation($employee->company_id, $employee->id, $startDate, $endDate);

        $uploadedPaths = [];
        $disk = 's3';

        try {
            return DB::transaction(function () use ($actor, $employee, $payload, $files, $status, $startDate, $endDate, $startTime, $endTime, $disk, &$uploadedPaths) {
                $absence = Absence::create([
                    'company_id' => $employee->company_id,
                    'user_id' => $employee->id,
                    'type' => Absence::TYPE_SICK_LEAVE,
                    'coverage_type' => $payload['coverage_type'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => $status,
                    'comment' => $payload['comment'] ?? null,
                    'counts_for_accrual' => true,
                    'created_by' => $actor->id,
                    'approved_by' => $status === Absence::STATUS_APPROVED ? $actor->id : null,
                    'approved_at' => $status === Absence::STATUS_APPROVED ? now() : null,
                ]);

                $documentIds = [];
                foreach ($files as $file) {
                    [$path, $extension, $mime] = $this->uploadDocument($file, $employee);
                    $uploadedPaths[] = $path;

                    $document = Document::create([
                        'company_id' => $employee->company_id,
                        'user_id' => $employee->id,
                        'title' => Str::limit(sprintf('Atestado médico - %s', $file->getClientOriginalName()), 180),
                        'category' => Document::CATEGORY_PERSONAL,
                        'status' => Document::STATUS_PENDING,
                        'mime_type' => $mime,
                        'ext' => $extension,
                        'size_bytes' => $file->getSize() ?: 0,
                        'path' => $path,
                        'storage_disk' => $disk,
                        'original_name' => $file->getClientOriginalName(),
                        'uploaded_by' => $actor->id,
                        'notes' => $payload['comment'] ?? null,
                    ]);

                    $documentIds[] = $document->id;
                }

                if ($documentIds !== []) {
                    $absence->documents()->attach($documentIds);
                }

                $this->auditLogService->log(
                    action: $status === Absence::STATUS_APPROVED ? 'medical_certificate.created_approved' : 'medical_certificate.created',
                    entityType: Absence::class,
                    entityId: $absence->id,
                    description: $status === Absence::STATUS_APPROVED ? 'Atestado médico lançado e aprovado' : 'Atestado médico solicitado',
                    newValues: $this->auditLogService->snapshot($absence->fresh()),
                    metadata: ['document_ids' => $documentIds],
                    companyId: $employee->company_id,
                );

                $this->absenceTimeEntryService->syncForAbsence($absence);

                return $absence->fresh(['documents', 'user']);
            });
        } catch (Throwable $exception) {
            foreach ($uploadedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }

            throw $exception;
        }
    }

    private function normalizeCoverage(array $payload): array
    {
        if ($payload['coverage_type'] === Absence::COVERAGE_HOURS) {
            return [
                $payload['date'],
                $payload['date'],
                $payload['start_time'],
                $payload['end_time'],
            ];
        }

        return [
            $payload['start_date'],
            $payload['end_date'] ?? $payload['start_date'],
            null,
            null,
        ];
    }

    private function uploadDocument(UploadedFile $file, User $employee): array
    {
        $id = (string) Str::ulid();
        $rawExtension = Str::lower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin'));
        $extension = preg_replace('/[^a-z0-9]+/', '', $rawExtension) ?: 'bin';
        $path = sprintf(
            'companies/%s/employees/%s/documents/%s.%s',
            $employee->company_id,
            $employee->id,
            $id,
            $extension,
        );

        $stream = fopen($file->getRealPath(), 'rb');
        $uploaded = $stream
            ? Storage::disk('s3')->put($path, $stream, [
                'visibility' => 'private',
                'ContentType' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
            ])
            : false;

        if (is_resource($stream)) {
            fclose($stream);
        }

        if (! $uploaded) {
            throw new RuntimeException('Não foi possível salvar o arquivo.');
        }

        return [
            $path,
            $extension,
            $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
        ];
    }

    private function assertNoClosedMonthlyClosure(string $companyId, string $startDate, string $endDate): void
    {
        foreach ($this->yearMonthPairs($startDate, $endDate) as [$year, $month]) {
            $exists = MonthlyClosure::query()
                ->where('company_id', $companyId)
                ->where('reference_year', $year)
                ->where('reference_month', $month)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'start_date' => 'Não é possível lançar atestado em mês com fechamento existente.',
                ]);
            }
        }
    }

    private function assertNoOverlappingAbsence(string $companyId, string $userId, string $startDate, string $endDate, ?string $ignoreId = null): void
    {
        $query = Absence::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereIn('status', Absence::BLOCKING_STATUSES)
            ->whereDate('start_date', '<=', $endDate)
            ->where(function ($query) use ($startDate) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $startDate);
            });

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'Já existe ausência ou atestado no período informado.',
            ]);
        }
    }

    private function assertNoOverlappingVacation(string $companyId, string $userId, string $startDate, string $endDate): void
    {
        $exists = VacationDay::query()
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->whereDate('date', '>=', $startDate)
            ->whereDate('date', '<=', $endDate)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'start_date' => 'Já existe férias no período informado.',
            ]);
        }
    }

    private function assertMedicalCertificate(Absence $absence): void
    {
        if (! $absence->isMedicalCertificate()) {
            abort(404);
        }
    }

    private function assertPending(Absence $absence): void
    {
        if ($absence->status !== Absence::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'O atestado não está pendente.',
            ]);
        }
    }

    private function yearMonthPairs(string $startDate, string $endDate): array
    {
        $cursor = CarbonImmutable::parse($startDate)->startOfMonth();
        $end = CarbonImmutable::parse($endDate)->startOfMonth();
        $pairs = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $pairs[] = [$cursor->year, $cursor->month];
            $cursor = $cursor->addMonth();
        }

        return $pairs;
    }
}
