<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\AreaManagerTeamEntriesRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeEntry\OvertimeCalculatorService;
use App\Services\UserVisibilityService;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TimeEntryController extends Controller
{
    public function __construct(
        protected UserVisibilityService $userVisibilityService
    ) {
    }

    public function teamEntries(
        AreaManagerTeamEntriesRequest $request,
        OvertimeCalculatorService $overtimeCalculator
    )
    {
        $user = $request->user();

        $query = TimeEntry::query()
            ->with(['user:id,name,email,company_id'])
            ->orderByDesc('clocked_at');
        $this->userVisibilityService->applyToUserOwnedQuery($query, $user);

        if ($request->filled('user_id')) {
            if ($user->hasRole('admin') || $this->userVisibilityService->canManageUserId($user, $request->user_id)) {
                $query->where('user_id', $request->user_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('source')) {
            $sources = array_filter(explode(',', $request->source));
            if (! empty($sources)) {
                $query->whereIn('source', $sources);
            }
        }

        $timezone = CompanyTime::companyTz($request);

        if ($request->filled('date_from')) {
            [$fromUtc] = CompanyTime::dayRangeToUtc(
                CompanyTime::normalizeDateInput($request->date_from, $timezone),
                $timezone
            );
            $query->where('clocked_at', '>=', $fromUtc->toDateTimeString());
        }

        if ($request->filled('date_to')) {
            [, $toUtc] = CompanyTime::dayRangeToUtc(
                CompanyTime::normalizeDateInput($request->date_to, $timezone),
                $timezone
            );
            $query->where('clocked_at', '<=', $toUtc->toDateTimeString());
        }

        $paginateAllForUser = $request->filled('user_id');
        $perPage = $paginateAllForUser
            ? max($query->count(), 1)
            : max(1, min((int) $request->get('per_page', 30), 200));

        $entries = $query->paginate(
            $perPage,
            ['*'],
            'page',
            $paginateAllForUser ? 1 : null
        );
        $this->attachDaySummaries($entries, CompanyTime::companyTz($request), $overtimeCalculator);
        $entries->setCollection(collect(TimeEntryResource::collectionArray($entries->getCollection())));

        return response()->json($entries);
    }

    private function attachDaySummaries(
        LengthAwarePaginator $entries,
        string $timezone,
        OvertimeCalculatorService $overtimeCalculator
    ): void {
        /** @var Collection<int, TimeEntry> $collection */
        $collection = $entries->getCollection();

        if ($collection->isEmpty()) {
            return;
        }

        $summariesByUser = [];

        /** @var Collection<string, Collection<int, TimeEntry>> $entriesByUser */
        $entriesByUser = $collection->groupBy('user_id');

        foreach ($entriesByUser as $userEntries) {
            /** @var TimeEntry $firstEntry */
            $firstEntry = $userEntries->first();
            /** @var User|null $employee */
            $employee = $firstEntry->user;

            if (! $employee) {
                continue;
            }

            $dates = $userEntries
                ->map(fn (TimeEntry $entry) => CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone)->toDateString())
                ->unique()
                ->sort()
                ->values();

            if ($dates->isEmpty()) {
                continue;
            }

            $from = CarbonImmutable::parse($dates->first(), $timezone)->startOfDay();
            $to = CarbonImmutable::parse($dates->last(), $timezone)->endOfDay();

            $overtime = $overtimeCalculator->calculateForEmployee($employee, $from, $to, true);

            $summariesByUser[$employee->id] = collect($overtime['days'] ?? [])
                ->keyBy('date')
                ->map(fn (array $day) => $day['summary'] ?? null)
                ->all();
        }

        $collection->transform(function (TimeEntry $entry) use ($summariesByUser, $timezone) {
            $workDate = CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone)->toDateString();

            $entry->setAttribute('work_date', $workDate);
            $entry->setAttribute('day_summary', $summariesByUser[$entry->user_id][$workDate] ?? null);

            return $entry;
        });
    }
}
