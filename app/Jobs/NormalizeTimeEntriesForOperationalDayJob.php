<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\TimeEntry\TimeEntryDayNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class NormalizeTimeEntriesForOperationalDayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public string $userId,
        public string $referenceClockedAt,
        public string $operationalDate
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("normalize-time-entries:{$this->userId}:{$this->operationalDate}"))
                ->releaseAfter(2),
        ];
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
