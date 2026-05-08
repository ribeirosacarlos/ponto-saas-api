<?php

namespace App\Actions\TimeEntries;

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;

class CreateTimeEntryAdjustmentAction
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {
    }

    /**
     * @param  array{
     *   clocked_at: CarbonImmutable,
     *   reason: string,
     *   proposed_clocked_at?: ?string,
     *   proposed_type?: ?string,
     *   proposed_latitude?: mixed,
     *   proposed_longitude?: mixed,
     *   proposed_source?: ?string,
     *   latitude?: mixed,
     *   longitude?: mixed,
     *   source?: ?string,
     *   device_type?: ?string,
     *   resolved_type?: ?string,
     *   event_kind?: ?string,
     *   user_shift_id?: ?string
     * }  $data
     */
    public function handle(User $targetUser, User $requestedBy, array $data): TimeEntry
    {
        $clockedAt = $data['clocked_at'];
        $resolvedType = $this->resolveType($targetUser, $clockedAt, $data['resolved_type'] ?? null, $data['proposed_type'] ?? null);

        $timeEntry = TimeEntry::create([
            'company_id' => $targetUser->company_id,
            'user_id' => $targetUser->id,
            'user_shift_id' => $data['user_shift_id'] ?? null,
            'clocked_at' => $clockedAt,
            'type' => $resolvedType,
            'event_kind' => $data['event_kind'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'source' => $data['source'] ?? 'adjustment',
            'device_type' => $data['device_type'] ?? null,
            'adjustment_status' => 'pending',
            'adjustment_reason' => $data['reason'],
            'adjustment_requested_by' => $requestedBy->id,
            'adjustment_requested_at' => now(),
            'proposed_clocked_at' => $data['proposed_clocked_at'] ?? null,
            'proposed_type' => $data['proposed_type'] ?? null,
            'proposed_latitude' => $data['proposed_latitude'] ?? null,
            'proposed_longitude' => $data['proposed_longitude'] ?? null,
            'proposed_source' => $data['proposed_source'] ?? null,
        ]);

        $this->auditLogService->log(
            action: 'time_entry.adjustment_requested',
            entityType: TimeEntry::class,
            entityId: $timeEntry->id,
            description: 'Solicitação de ajuste de ponto criada.',
            newValues: $this->auditLogService->snapshot([
                'user_id' => $timeEntry->user_id,
                'clocked_at' => optional($timeEntry->clocked_at)->toIso8601String(),
                'type' => $timeEntry->type,
                'event_kind' => $timeEntry->event_kind,
                'source' => $timeEntry->source,
                'adjustment_status' => $timeEntry->adjustment_status,
                'adjustment_reason' => $timeEntry->adjustment_reason,
                'proposed_clocked_at' => optional($timeEntry->proposed_clocked_at)->toIso8601String(),
                'proposed_type' => $timeEntry->proposed_type,
                'proposed_source' => $timeEntry->proposed_source,
            ]),
            metadata: [
                'requested_for_user_id' => $targetUser->id,
                'requested_by_user_id' => $requestedBy->id,
                'requested_by_same_user' => $targetUser->is($requestedBy),
            ],
            companyId: $targetUser->company_id,
        );

        return $timeEntry;
    }

    private function resolveType(User $user, CarbonImmutable $clockedAt, ?string $resolvedType, ?string $proposedType): string
    {
        if (in_array($resolvedType, ['in', 'out'], true)) {
            return $resolvedType;
        }

        if (in_array($proposedType, ['in', 'out'], true)) {
            return $proposedType;
        }

        $lastEntry = TimeEntry::query()
            ->excludeRejected()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->whereDate('clocked_at', $clockedAt->toDateString())
            ->where('clocked_at', '<=', $clockedAt->toDateTimeString())
            ->whereIn('type', ['in', 'out'])
            ->orderByDesc('clocked_at')
            ->first();

        if (! $lastEntry) {
            return 'in';
        }

        return $lastEntry->type === 'in' ? 'out' : 'in';
    }
}
