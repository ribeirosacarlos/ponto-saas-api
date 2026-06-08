<?php

namespace App\Http\Controllers\Api\AreaManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\AreaManagerTeamEntriesRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeEntry\OvertimeCalculatorService;
use App\Services\UserVisibilityService;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TimeEntryController extends Controller
{
    public function __construct(
        protected UserVisibilityService $userVisibilityService
    ) {}

    public function teamEntries(
        AreaManagerTeamEntriesRequest $request,
        OvertimeCalculatorService $overtimeCalculator
    ) {
        $user = $request->user();
        $fromLocal = null;
        $toLocal = null;

        $query = TimeEntry::query()
            ->with(['user:id,name,email,company_id'])
            ->excludeRejected()
            ->orderByDesc('clocked_at');
        $this->userVisibilityService->applyToUserOwnedQuery($query, $user);

        if ($request->filled('user_id')) {
            $requestedEmployee = $this->userVisibilityService
                ->visibleUsersQuery($user)
                ->whereKey($request->user_id)
                ->first();

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
            $fromLocal = CarbonImmutable::parse(
                CompanyTime::normalizeDateInput($request->date_from, $timezone),
                $timezone
            )->startOfDay();
            [$fromUtc] = CompanyTime::dayRangeToUtc(
                CompanyTime::normalizeDateInput($request->date_from, $timezone),
                $timezone
            );
            $query->where('clocked_at', '>=', $fromUtc->toDateTimeString());
        }

        if ($request->filled('date_to')) {
            $toLocal = CarbonImmutable::parse(
                CompanyTime::normalizeDateInput($request->date_to, $timezone),
                $timezone
            )->endOfDay();
            [, $toUtc] = CompanyTime::dayRangeToUtc(
                CompanyTime::normalizeDateInput($request->date_to, $timezone),
                $timezone
            );
            $query->where('clocked_at', '<=', $toUtc->toDateTimeString());
        }

        if ($request->filled('user_id')) {
            return response()->json(
                $this->groupUserEntriesByDay($query->get(), $timezone, $overtimeCalculator, $request, $requestedEmployee ?? null, $fromLocal, $toLocal)
            );
        }

        $perPage = max(1, min((int) $request->get('per_page', 30), 200));
        $entries = $query->paginate(
            $perPage,
            ['*'],
            'page'
        );
        $this->attachDaySummaries($entries->getCollection(), $timezone, $overtimeCalculator);
        $payloadEntries = collect(TimeEntryResource::collectionArray($entries->getCollection()));
        $virtualEntries = collect();

        [$virtualFrom, $virtualTo] = $this->resolveFlatVirtualRange($user, $timezone, $fromLocal, $toLocal);
        $virtualEntries = collect($virtualFrom && $virtualTo
            ? $this->buildVisibleVirtualAbsenceEntries($user, $virtualFrom, $virtualTo, $timezone, $overtimeCalculator)
            : []);
        $payloadEntries = $payloadEntries
            ->merge($virtualEntries)
            ->sortByDesc('clocked_at')
            ->values();

        $entries = new LengthAwarePaginator(
            $payloadEntries,
            $entries->total() + $virtualEntries->count(),
            $entries->perPage(),
            $entries->currentPage(),
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json($entries);
    }

    /**
     * @param  Collection<int, TimeEntry>  $collection
     * @return array<string, mixed>
     */
    private function groupUserEntriesByDay(
        Collection $collection,
        string $timezone,
        OvertimeCalculatorService $overtimeCalculator,
        AreaManagerTeamEntriesRequest $request,
        ?User $requestedEmployee,
        ?CarbonImmutable $fromLocal,
        ?CarbonImmutable $toLocal
    ): array {
        $summariesByUser = $this->buildSummariesByUser($collection, $timezone, $overtimeCalculator);
        $overtimeDaysByDate = [];

        if ($requestedEmployee) {
            [$from, $to] = $this->resolveGroupedRange($requestedEmployee, $collection, $timezone, $fromLocal, $toLocal);
            $overtime = $overtimeCalculator->calculateForEmployee($requestedEmployee, $from, $to, true);
            $overtimeDaysByDate = collect($overtime['days'] ?? [])->keyBy('date')->all();
            $summariesByUser[$requestedEmployee->id] = collect($overtime['days'] ?? [])
                ->keyBy('date')
                ->map(fn (array $day) => $day['summary'] ?? null)
                ->all();
        }

        $groups = $collection
            ->groupBy(fn (TimeEntry $entry) => CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone)->toDateString())
            ->map(function (Collection $entries, string $date) use ($summariesByUser, $timezone) {
                /** @var TimeEntry $firstEntry */
                $firstEntry = $entries->first();
                /** @var User|null $employee */
                $employee = $firstEntry->user;

                $entries->each(function (TimeEntry $entry) use ($timezone) {
                    $entry->setAttribute(
                        'work_date',
                        CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone)->toDateString()
                    );
                });

                return [
                    'date' => $date,
                    'employee_id' => $firstEntry->user_id,
                    'user' => $employee ? [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                    ] : null,
                    'day_summary' => $summariesByUser[$firstEntry->user_id][$date] ?? null,
                    'entries' => TimeEntryResource::collectionArray($entries),
                ];
            })
            ->keyBy('date');

        if ($requestedEmployee) {
            foreach ($overtimeDaysByDate as $date => $day) {
                $virtualEntries = $day['virtual_entries'] ?? [];
                $summary = $day['summary'] ?? null;

                if ($virtualEntries === [] || ! ($summary['is_absence'] ?? false)) {
                    continue;
                }

                $virtualEntries = collect($virtualEntries)
                    ->sortByDesc('clocked_at')
                    ->values()
                    ->all();

                if ($groups->has($date)) {
                    $group = $groups->get($date);
                    $group['entries'] = collect(array_merge($group['entries'], $virtualEntries))
                        ->sortByDesc('clocked_at')
                        ->values()
                        ->all();
                    $groups->put($date, $group);

                    continue;
                }

                $groups->put($date, [
                    'date' => $date,
                    'employee_id' => $requestedEmployee->id,
                    'user' => [
                        'id' => $requestedEmployee->id,
                        'name' => $requestedEmployee->name,
                        'email' => $requestedEmployee->email,
                    ],
                    'day_summary' => $summary,
                    'entries' => $virtualEntries,
                ]);
            }
        }

        $groups = $groups
            ->sortByDesc('date')
            ->values();

        return [
            'data' => $groups->all(),
            'total_days' => $groups->count(),
            'total_entries' => $groups->sum(fn (array $group) => count($group['entries'] ?? [])),
        ];
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function resolveFlatVirtualRange(
        User $actor,
        string $timezone,
        ?CarbonImmutable $fromLocal,
        ?CarbonImmutable $toLocal
    ): array {
        if ($fromLocal && $toLocal) {
            return [$fromLocal, $toLocal];
        }

        $visibleUsers = $this->userVisibilityService
            ->visibleUsersQuery($actor)
            ->get(['id']);
        $absenceBounds = $this->absenceDateBoundsForUsers($visibleUsers, $timezone);

        if (! $absenceBounds) {
            return [$fromLocal, $toLocal];
        }

        return [
            $fromLocal ?? $absenceBounds[0],
            $toLocal ?? $absenceBounds[1],
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveGroupedRange(
        User $employee,
        Collection $collection,
        string $timezone,
        ?CarbonImmutable $fromLocal,
        ?CarbonImmutable $toLocal
    ): array {
        $entryDates = $collection
            ->map(fn (TimeEntry $entry) => CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone)->startOfDay())
            ->values();
        $absenceBounds = $this->absenceDateBoundsForUsers(collect([$employee]), $timezone);

        $fromCandidates = $entryDates->all();
        $toCandidates = $entryDates->all();

        if ($absenceBounds) {
            $fromCandidates[] = $absenceBounds[0];
            $toCandidates[] = $absenceBounds[1];
        }

        $from = $fromLocal ?? collect($fromCandidates)->sortBy(fn (CarbonImmutable $date) => $date->timestamp)->first();
        $to = $toLocal ?? collect($toCandidates)->sortByDesc(fn (CarbonImmutable $date) => $date->timestamp)->first();

        $today = CarbonImmutable::now($timezone);

        return [
            ($from ?? $today)->startOfDay(),
            ($to ?? $from ?? $today)->endOfDay(),
        ];
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    private function absenceDateBoundsForUsers(Collection $users, string $timezone): ?array
    {
        if ($users->isEmpty()) {
            return null;
        }

        $bounds = Absence::query()
            ->whereIn('user_id', $users->pluck('id')->all())
            ->whereIn('status', Absence::EFFECTIVE_STATUSES)
            ->selectRaw('MIN(start_date) as first_date, MAX(COALESCE(end_date, start_date)) as last_date')
            ->first();

        if (! $bounds?->first_date || ! $bounds?->last_date) {
            return null;
        }

        return [
            CarbonImmutable::parse($bounds->first_date, $timezone)->startOfDay(),
            CarbonImmutable::parse($bounds->last_date, $timezone)->endOfDay(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildVisibleVirtualAbsenceEntries(
        User $actor,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $timezone,
        OvertimeCalculatorService $overtimeCalculator
    ): array {
        $visibleUserIds = $this->userVisibilityService
            ->visibleUsersQuery($actor)
            ->pluck('id');

        if ($visibleUserIds->isEmpty()) {
            return [];
        }

        $users = User::query()
            ->whereIn('id', $visibleUserIds)
            ->whereIn('id', function ($query) use ($from, $to) {
                $query->select('user_id')
                    ->from('absences')
                    ->whereIn('status', Absence::EFFECTIVE_STATUSES)
                    ->whereDate('start_date', '<=', $to->toDateString())
                    ->where(function ($absenceQuery) use ($from) {
                        $absenceQuery->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $from->toDateString());
                    });
            })
            ->get();

        return $users
            ->flatMap(function (User $employee) use ($from, $to, $overtimeCalculator) {
                $overtime = $overtimeCalculator->calculateForEmployee($employee, $from, $to, true);

                return collect($overtime['days'] ?? [])
                    ->filter(fn (array $day) => ($day['virtual_entries'] ?? []) !== [] && (($day['summary']['is_absence'] ?? false) === true))
                    ->flatMap(function (array $day) {
                        $summary = $day['summary'] ?? null;

                        return collect($day['virtual_entries'])
                            ->map(function (array $entry) use ($summary) {
                                $entry['day_summary'] = $summary;

                                return $entry;
                            });
                    });
            })
            ->values()
            ->all();
    }

    private function attachDaySummaries(
        Collection $collection,
        string $timezone,
        OvertimeCalculatorService $overtimeCalculator
    ): void {
        $summariesByUser = $this->buildSummariesByUser($collection, $timezone, $overtimeCalculator);

        $collection->transform(function (TimeEntry $entry) use ($summariesByUser, $timezone) {
            $workDate = CarbonImmutable::instance($entry->clocked_at)->setTimezone($timezone)->toDateString();

            $entry->setAttribute('work_date', $workDate);
            $entry->setAttribute('day_summary', $summariesByUser[$entry->user_id][$workDate] ?? null);

            return $entry;
        });
    }

    /**
     * @param  Collection<int, TimeEntry>  $collection
     * @return array<string, array<string, array<string, mixed>|null>>
     */
    private function buildSummariesByUser(
        Collection $collection,
        string $timezone,
        OvertimeCalculatorService $overtimeCalculator
    ): array {

        if ($collection->isEmpty()) {
            return [];
        }

        $summariesByUser = [];

        /** @var Collection<string, Collection<int, TimeEntry>> $entriesByUser */
        $entriesByUser = $collection->groupBy('user_id');

        foreach ($entriesByUser as $userEntries) {
            /** @var TimeEntry $firstEntry */
            $firstEntry = $userEntries->first();
            /** @var User|null $employee  */
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

        return $summariesByUser;
    }
}
