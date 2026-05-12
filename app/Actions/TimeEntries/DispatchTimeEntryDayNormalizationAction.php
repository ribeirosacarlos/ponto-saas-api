<?php

namespace App\Actions\TimeEntries;

use App\Jobs\NormalizeTimeEntriesForOperationalDayJob;
use App\Models\User;
use App\Services\TimeEntry\TimeEntryDayNormalizer;
use Carbon\CarbonImmutable;

class DispatchTimeEntryDayNormalizationAction
{
    public function __construct(
        private readonly TimeEntryDayNormalizer $normalizer
    ) {}

    public function handle(User $user, CarbonImmutable $reference): void
    {
        $day = $this->normalizer->resolveOperationalDayKey($user, $reference);

        NormalizeTimeEntriesForOperationalDayJob::dispatch(
            $user->id,
            $reference->toIso8601String(),
            $day['base_date']
        )->afterCommit();
    }
}
