<?php

namespace App\Observers;

use App\Actions\TimeEntries\DispatchTimeEntryDayNormalizationAction;
use App\Models\TimeEntry;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class TimeEntryObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly DispatchTimeEntryDayNormalizationAction $dispatchTimeEntryDayNormalization
    ) {}

    public function updated(TimeEntry $timeEntry): void
    {
        if (! $timeEntry->wasChanged([
            'clocked_at',
            'type',
            'event_kind',
            'adjustment_status',
            'user_id',
            'company_id',
        ])) {
            return;
        }

        $this->dispatchNormalization($timeEntry);
    }

    public function deleted(TimeEntry $timeEntry): void
    {
        $this->dispatchNormalization($timeEntry);
    }

    public function restored(TimeEntry $timeEntry): void
    {
        $this->dispatchNormalization($timeEntry);
    }

    private function dispatchNormalization(TimeEntry $timeEntry): void
    {
        if (! $timeEntry->clocked_at || ! $timeEntry->user) {
            return;
        }

        $this->dispatchTimeEntryDayNormalization->handle(
            $timeEntry->user,
            $timeEntry->clocked_at->toImmutable()
        );
    }
}
