<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TimeEntry\TimeEntryDayNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NormalizeTimeEntriesForOperationalDayJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public string $userId,
        public string $referenceClockedAt,
        public string $operationalDate
    ) {}

    public function uniqueId(): string
    {
        return "{$this->userId}:{$this->operationalDate}";
    }

    public function handle(TimeEntryDayNormalizer $normalizer): void
    {
        $user = User::query()
            ->with('company')
            ->find($this->userId);

        if (! $user) {
            return;
        }

        $normalizer->normalizeForReference(
            $user,
            CarbonImmutable::parse($this->referenceClockedAt)
        );
    }
}
