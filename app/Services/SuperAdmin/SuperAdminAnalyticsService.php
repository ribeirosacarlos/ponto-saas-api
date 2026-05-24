<?php

namespace App\Services\SuperAdmin;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SuperAdminAnalyticsService
{
    protected const SERIES_WINDOWS = [30, 60, 90];

    protected const RECENT_EVENT_ACTIONS = [
        'platform.company_created' => 'company_created',
        'platform.company_blocked' => 'company_blocked',
        'platform.company_unblocked' => 'company_unblocked',
    ];

    public function dashboardSummary(): array
    {
        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay();
        $day7 = $now->copy()->subDays(7);
        $day30 = $now->copy()->subDays(30);
        $day14 = $now->copy()->subDays(14);

        $companyBase = DB::table('companies')->whereNull('deleted_at');
        $subscriptionBase = DB::table('subscriptions')->whereNull('deleted_at');

        $totalCompanies = (clone $companyBase)->count();
        $blockedCompanies = (clone $companyBase)->where('is_blocked', true)->count();
        $newCompanies30d = (clone $companyBase)->where('created_at', '>=', $day30)->count();

        $companiesActive7d = $this->distinctCompanyActivityCount($day7);
        $companiesActive30d = $this->distinctCompanyActivityCount($day30);

        $employeesTotal = DB::table('users')->whereNotNull('company_id')->count();
        $employeesActive30d = $this->distinctActiveUsersCount($day30);

        $timeEntriesToday = DB::table('time_entries')->where('clocked_at', '>=', $todayStart)->count();
        $timeEntries7d = DB::table('time_entries')->where('clocked_at', '>=', $day7)->count();
        $timeEntries30d = DB::table('time_entries')->where('clocked_at', '>=', $day30)->count();

        $companiesTrialing = (clone $subscriptionBase)
            ->where('status', SubscriptionStatus::TRIALING->value)
            ->count();

        $companiesPaying = (clone $subscriptionBase)
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->count();

        $companiesPastDue = (clone $subscriptionBase)
            ->where('status', SubscriptionStatus::PAST_DUE->value)
            ->count();

        $expiringSoon = (clone $subscriptionBase)
            ->whereNotNull('current_period_end')
            ->whereBetween('current_period_end', [$now, $now->copy()->addDays(7)])
            ->count();

        $atRiskCompanies = DB::table('companies as c')
            ->leftJoin('subscriptions as s', function ($join) {
                $join->on('s.company_id', '=', 'c.id')
                    ->whereNull('s.deleted_at');
            })
            ->leftJoinSub($this->lastActivitySubquery(), 'activity', fn ($join) => $join->on('activity.company_id', '=', 'c.id'))
            ->whereNull('c.deleted_at')
            ->where(function ($query) use ($day14) {
                $query->where('s.status', SubscriptionStatus::PAST_DUE->value)
                    ->orWhere('c.is_blocked', true)
                    ->orWhereNull('activity.last_activity_at')
                    ->orWhere('activity.last_activity_at', '<', $day14);
            })
            ->distinct('c.id')
            ->count('c.id');

        $revenue = $this->estimatedRevenueSnapshot();

        return [
            'generated_at' => $now->toISOString(),
            'companies' => [
                'total' => $totalCompanies,
                'blocked' => $blockedCompanies,
                'new_last_30_days' => $newCompanies30d,
                'active_last_7_days' => $companiesActive7d,
                'active_last_30_days' => $companiesActive30d,
                'trialing' => $companiesTrialing,
                'paying' => $companiesPaying,
                'past_due' => $companiesPastDue,
                'expiring_in_7_days' => $expiringSoon,
                'at_risk' => $atRiskCompanies,
            ],
            'employees' => [
                'total' => $employeesTotal,
                'active_last_30_days' => $employeesActive30d,
            ],
            'time_entries' => [
                'today' => $timeEntriesToday,
                'last_7_days' => $timeEntries7d,
                'last_30_days' => $timeEntries30d,
            ],
            'revenue' => $revenue,
            'time_entries_series' => $this->timeEntriesSeries($now),
            'active_companies_series' => $this->activeCompaniesSeries($now),
            'subscription_status_breakdown' => $this->subscriptionStatusBreakdown(),
            'plan_breakdown' => $this->planBreakdown(),
            'recent_events' => $this->recentEvents($now),
            'top_companies_by_activity' => $this->topCompaniesByActivity($day30),
            'top_companies_by_risk' => $this->topCompaniesByRisk($now),
        ];
    }

    public function companyMetricsPage(array $filters = []): LengthAwarePaginator
    {
        $now = Carbon::now();
        $todayStart = $now->copy()->startOfDay()->toDateTimeString();
        $day30 = $now->copy()->subDays(30)->toDateTimeString();

        $query = Company::query()
            ->select('companies.*')
            ->leftJoin('subscriptions', function ($join) {
                $join->on('subscriptions.company_id', '=', 'companies.id')
                    ->whereNull('subscriptions.deleted_at');
            })
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->leftJoinSub($this->employeesCountSubquery(), 'employees_metrics', fn ($join) => $join->on('employees_metrics.company_id', '=', 'companies.id'))
            ->leftJoinSub($this->activeEmployeesSubquery($day30), 'active_employee_metrics', fn ($join) => $join->on('active_employee_metrics.company_id', '=', 'companies.id'))
            ->leftJoinSub($this->timeEntriesTodaySubquery($todayStart), 'time_entries_today_metrics', fn ($join) => $join->on('time_entries_today_metrics.company_id', '=', 'companies.id'))
            ->leftJoinSub($this->timeEntries30dSubquery($day30), 'time_entries_30d_metrics', fn ($join) => $join->on('time_entries_30d_metrics.company_id', '=', 'companies.id'))
            ->leftJoinSub($this->lastActivitySubquery(), 'activity_metrics', fn ($join) => $join->on('activity_metrics.company_id', '=', 'companies.id'))
            ->whereNull('companies.deleted_at')
            ->addSelect([
                DB::raw('COALESCE(employees_metrics.employees_count, 0) as employees_count'),
                DB::raw('COALESCE(active_employee_metrics.active_employees_30d, 0) as active_employees_30d'),
                DB::raw('COALESCE(time_entries_today_metrics.time_entries_today, 0) as time_entries_today'),
                DB::raw('COALESCE(time_entries_30d_metrics.time_entries_30d, 0) as time_entries_30d'),
                DB::raw('activity_metrics.last_activity_at as last_activity_at'),
                DB::raw('subscriptions.status as subscription_status'),
                DB::raw('plans.name as plan_name'),
                DB::raw('plans.slug as plan_slug'),
                DB::raw('plans.price_cents as plan_price_cents'),
            ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search) {
                $builder->where('companies.name', 'like', "%{$search}%")
                    ->orWhere('companies.slug', 'like', "%{$search}%")
                    ->orWhere('companies.email', 'like', "%{$search}%")
                    ->orWhere('companies.document', 'like', "%{$search}%");
            });
        }

        if (($filters['status'] ?? null) === 'blocked') {
            $query->where('companies.is_blocked', true);
        } elseif (($filters['status'] ?? null) === 'active') {
            $query->where('companies.is_blocked', false);
        } elseif (! empty($filters['status'])) {
            $query->where('subscriptions.status', $filters['status']);
        }

        if (($filters['activity'] ?? null) === 'active') {
            $query->whereNotNull('activity_metrics.last_activity_at')
                ->where('activity_metrics.last_activity_at', '>=', $day30);
        } elseif (($filters['activity'] ?? null) === 'inactive') {
            $query->where(function ($builder) use ($day30) {
                $builder->whereNull('activity_metrics.last_activity_at')
                    ->orWhere('activity_metrics.last_activity_at', '<', $day30);
            });
        }

        [$sortColumn, $direction] = $this->resolveSort($filters['sort'] ?? '-created_at');
        $query->orderBy($sortColumn, $direction);

        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));
        $paginator = $query->paginate($perPage)->withQueryString();

        $paginator->getCollection()->transform(function ($company) use ($day30) {
            $company->subscription_status_label = $this->subscriptionStatusLabel($company->subscription_status);
            $company->health_status = $this->healthStatus($company, $day30);

            return $company;
        });

        return $paginator;
    }

    protected function resolveSort(string $sort): array
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $key = ltrim($sort, '-');

        $map = [
            'name' => 'companies.name',
            'created_at' => 'companies.created_at',
            'updated_at' => 'companies.updated_at',
            'employees_count' => 'employees_count',
            'active_employees_30d' => 'active_employees_30d',
            'time_entries_30d' => 'time_entries_30d',
            'last_activity_at' => 'last_activity_at',
        ];

        return [$map[$key] ?? 'companies.created_at', $direction];
    }

    protected function healthStatus(object $company, string $activeThreshold): string
    {
        if ($company->is_blocked || $company->subscription_status === SubscriptionStatus::PAST_DUE->value) {
            return 'critical';
        }

        if (empty($company->last_activity_at) || $company->last_activity_at < $activeThreshold) {
            return 'warning';
        }

        return 'healthy';
    }

    protected function subscriptionStatusLabel(?string $status): string
    {
        return match ($status) {
            SubscriptionStatus::TRIALING->value => 'Trial',
            SubscriptionStatus::ACTIVE->value => 'Ativa',
            SubscriptionStatus::PAST_DUE->value => 'Inadimplente',
            SubscriptionStatus::CANCELED->value => 'Cancelada',
            default => 'Sem assinatura',
        };
    }

    protected function distinctCompanyActivityCount(Carbon $from): int
    {
        return DB::table('time_entries')
            ->where('clocked_at', '>=', $from)
            ->distinct('company_id')
            ->count('company_id');
    }

    protected function distinctActiveUsersCount(Carbon $from): int
    {
        return DB::table('time_entries')
            ->where('clocked_at', '>=', $from)
            ->distinct('user_id')
            ->count('user_id');
    }

    protected function timeEntriesSeries(Carbon $now): array
    {
        return $this->seriesWindows($now, fn (Carbon $from, Carbon $to) => $this->timeEntriesDailyPoints($from, $to));
    }

    protected function activeCompaniesSeries(Carbon $now): array
    {
        return $this->seriesWindows($now, fn (Carbon $from, Carbon $to) => $this->activeCompaniesDailyPoints($from, $to));
    }

    protected function seriesWindows(Carbon $now, callable $resolver): array
    {
        $to = $now->copy()->endOfDay();
        $windows = [];

        foreach (self::SERIES_WINDOWS as $days) {
            $from = $now->copy()->subDays($days - 1)->startOfDay();

            $windows["{$days}d"] = [
                'days' => $days,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'points' => $resolver($from, $to),
            ];
        }

        return [
            'granularity' => 'day',
            'windows' => $windows,
        ];
    }

    protected function timeEntriesDailyPoints(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('time_entries')
            ->selectRaw('DATE(clocked_at) as metric_date, COUNT(*) as total')
            ->whereBetween('clocked_at', [$from, $to])
            ->groupByRaw('DATE(clocked_at)')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->metric_date => (int) $row->total]);

        return $this->fillDailyPoints($from, $to, $rows->all(), 'time_entries');
    }

    protected function activeCompaniesDailyPoints(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('time_entries')
            ->selectRaw('DATE(clocked_at) as metric_date, COUNT(DISTINCT company_id) as total')
            ->whereBetween('clocked_at', [$from, $to])
            ->groupByRaw('DATE(clocked_at)')
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->metric_date => (int) $row->total]);

        return $this->fillDailyPoints($from, $to, $rows->all(), 'active_companies');
    }

    protected function fillDailyPoints(Carbon $from, Carbon $to, array $totalsByDate, string $valueKey): array
    {
        $points = [];
        $cursor = $from->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($to)) {
            $date = $cursor->toDateString();

            $points[] = [
                'date' => $date,
                $valueKey => (int) ($totalsByDate[$date] ?? 0),
            ];

            $cursor->addDay();
        }

        return $points;
    }

    protected function subscriptionStatusBreakdown(): array
    {
        $rows = DB::table('companies')
            ->leftJoin('subscriptions', function ($join) {
                $join->on('subscriptions.company_id', '=', 'companies.id')
                    ->whereNull('subscriptions.deleted_at');
            })
            ->whereNull('companies.deleted_at')
            ->selectRaw("COALESCE(subscriptions.status, 'none') as status, COUNT(companies.id) as total")
            ->groupByRaw("COALESCE(subscriptions.status, 'none')")
            ->get()
            ->mapWithKeys(fn ($row) => [(string) $row->status => (int) $row->total]);

        $statuses = [
            SubscriptionStatus::TRIALING->value,
            SubscriptionStatus::ACTIVE->value,
            SubscriptionStatus::PAST_DUE->value,
            SubscriptionStatus::CANCELED->value,
            'none',
        ];

        return array_map(fn (string $status) => [
            'status' => $status,
            'label' => $this->subscriptionStatusLabel($status === 'none' ? null : $status),
            'total' => (int) ($rows[$status] ?? 0),
        ], $statuses);
    }

    protected function planBreakdown(): array
    {
        return DB::table('companies')
            ->leftJoin('subscriptions', function ($join) {
                $join->on('subscriptions.company_id', '=', 'companies.id')
                    ->whereNull('subscriptions.deleted_at');
            })
            ->leftJoin('plans', function ($join) {
                $join->on('plans.id', '=', 'subscriptions.plan_id')
                    ->whereNull('plans.deleted_at');
            })
            ->whereNull('companies.deleted_at')
            ->select([
                'plans.id as plan_id',
                'plans.slug as plan_slug',
                'plans.name as plan_name',
                'plans.billing_interval as billing_interval',
            ])
            ->selectRaw('COUNT(companies.id) as total')
            ->groupBy('plans.id', 'plans.slug', 'plans.name', 'plans.billing_interval')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'plan_id' => $row->plan_id,
                'plan_slug' => $row->plan_slug,
                'plan_name' => $row->plan_name ?? 'Sem plano',
                'billing_interval' => $row->billing_interval,
                'total' => (int) $row->total,
            ])
            ->all();
    }

    protected function recentEvents(Carbon $now, int $limit = 20): array
    {
        $from = $now->copy()->subDays(30);

        $events = collect()
            ->merge($this->recentAuditEvents($from))
            ->merge($this->recentCompanyCreatedEvents($from))
            ->merge($this->recentCompanyBlockedEvents($from))
            ->merge($this->recentSubscriptionPastDueEvents($from));

        return $events
            ->unique(fn (array $event) => $event['type'].':'.($event['company']['id'] ?? '').':'.substr($event['occurred_at'], 0, 10))
            ->sortByDesc('occurred_at')
            ->values()
            ->take($limit)
            ->all();
    }

    protected function recentAuditEvents(Carbon $from): array
    {
        return DB::table('audit_logs')
            ->leftJoin('companies', 'companies.id', '=', 'audit_logs.target_company_id')
            ->whereIn('audit_logs.action', array_keys(self::RECENT_EVENT_ACTIONS))
            ->where('audit_logs.created_at', '>=', $from)
            ->orderByDesc('audit_logs.created_at')
            ->limit(20)
            ->get([
                'audit_logs.id',
                'audit_logs.action',
                'audit_logs.description',
                'audit_logs.created_at',
                'companies.id as company_id',
                'companies.name as company_name',
                'companies.slug as company_slug',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'type' => self::RECENT_EVENT_ACTIONS[$row->action],
                'occurred_at' => $this->toIsoString($row->created_at),
                'company' => $this->companySummary($row->company_id, $row->company_name, $row->company_slug),
                'description' => $row->description,
                'source' => 'audit_log',
            ])
            ->all();
    }

    protected function recentCompanyCreatedEvents(Carbon $from): array
    {
        return DB::table('companies')
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $from)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'created_at'])
            ->map(fn ($row) => [
                'id' => 'company_created:'.$row->id,
                'type' => 'company_created',
                'occurred_at' => $this->toIsoString($row->created_at),
                'company' => $this->companySummary($row->id, $row->name, $row->slug),
                'description' => 'Empresa criada.',
                'source' => 'companies.created_at',
            ])
            ->all();
    }

    protected function recentCompanyBlockedEvents(Carbon $from): array
    {
        return DB::table('companies')
            ->whereNull('deleted_at')
            ->where('is_blocked', true)
            ->where('blocked_at', '>=', $from)
            ->orderByDesc('blocked_at')
            ->limit(20)
            ->get(['id', 'name', 'slug', 'blocked_at', 'blocked_reason'])
            ->map(fn ($row) => [
                'id' => 'company_blocked:'.$row->id,
                'type' => 'company_blocked',
                'occurred_at' => $this->toIsoString($row->blocked_at),
                'company' => $this->companySummary($row->id, $row->name, $row->slug),
                'description' => $row->blocked_reason ? 'Empresa bloqueada: '.$row->blocked_reason : 'Empresa bloqueada.',
                'source' => 'companies.blocked_at',
            ])
            ->all();
    }

    protected function recentSubscriptionPastDueEvents(Carbon $from): array
    {
        return DB::table('subscriptions')
            ->join('companies', 'companies.id', '=', 'subscriptions.company_id')
            ->whereNull('subscriptions.deleted_at')
            ->whereNull('companies.deleted_at')
            ->where('subscriptions.status', SubscriptionStatus::PAST_DUE->value)
            ->where('subscriptions.past_due_since', '>=', $from)
            ->orderByDesc('subscriptions.past_due_since')
            ->limit(20)
            ->get([
                'subscriptions.id',
                'subscriptions.past_due_since',
                'companies.id as company_id',
                'companies.name as company_name',
                'companies.slug as company_slug',
            ])
            ->map(fn ($row) => [
                'id' => 'subscription_past_due:'.$row->id,
                'type' => 'subscription_past_due',
                'occurred_at' => $this->toIsoString($row->past_due_since),
                'company' => $this->companySummary($row->company_id, $row->company_name, $row->company_slug),
                'description' => 'Assinatura entrou em atraso.',
                'source' => 'subscriptions.past_due_since',
            ])
            ->all();
    }

    protected function topCompaniesByActivity(Carbon $from, int $limit = 10): array
    {
        return DB::table('companies')
            ->leftJoin('subscriptions', function ($join) {
                $join->on('subscriptions.company_id', '=', 'companies.id')
                    ->whereNull('subscriptions.deleted_at');
            })
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->leftJoinSub($this->timeEntries30dSubquery($from->toDateTimeString()), 'time_entries_30d_metrics', fn ($join) => $join->on('time_entries_30d_metrics.company_id', '=', 'companies.id'))
            ->leftJoinSub($this->activeEmployeesSubquery($from->toDateTimeString()), 'active_employee_metrics', fn ($join) => $join->on('active_employee_metrics.company_id', '=', 'companies.id'))
            ->leftJoinSub($this->lastActivitySubquery(), 'activity_metrics', fn ($join) => $join->on('activity_metrics.company_id', '=', 'companies.id'))
            ->whereNull('companies.deleted_at')
            ->orderByDesc('time_entries_30d')
            ->orderByDesc('active_billable_users_30d')
            ->orderBy('companies.name')
            ->limit($limit)
            ->get([
                'companies.id',
                'companies.name',
                'companies.slug',
                'subscriptions.status as subscription_status',
                'plans.slug as plan_slug',
                DB::raw('COALESCE(time_entries_30d_metrics.time_entries_30d, 0) as time_entries_30d'),
                DB::raw('COALESCE(active_employee_metrics.active_employees_30d, 0) as active_billable_users_30d'),
                DB::raw('activity_metrics.last_activity_at as last_activity_at'),
            ])
            ->map(fn ($row) => [
                'company' => $this->companySummary($row->id, $row->name, $row->slug),
                'subscription_status' => $row->subscription_status,
                'plan_slug' => $row->plan_slug,
                'time_entries_30d' => (int) $row->time_entries_30d,
                'active_billable_users_30d' => (int) $row->active_billable_users_30d,
                'last_activity_at' => $this->toIsoString($row->last_activity_at),
            ])
            ->all();
    }

    protected function topCompaniesByRisk(Carbon $now, int $limit = 10): array
    {
        $day14 = $now->copy()->subDays(14)->toDateTimeString();

        return DB::table('companies')
            ->leftJoin('subscriptions', function ($join) {
                $join->on('subscriptions.company_id', '=', 'companies.id')
                    ->whereNull('subscriptions.deleted_at');
            })
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->leftJoinSub($this->timeEntries30dSubquery($now->copy()->subDays(30)->toDateTimeString()), 'time_entries_30d_metrics', fn ($join) => $join->on('time_entries_30d_metrics.company_id', '=', 'companies.id'))
            ->leftJoinSub($this->lastActivitySubquery(), 'activity_metrics', fn ($join) => $join->on('activity_metrics.company_id', '=', 'companies.id'))
            ->whereNull('companies.deleted_at')
            ->select([
                'companies.id',
                'companies.name',
                'companies.slug',
                'companies.is_blocked',
                'subscriptions.status as subscription_status',
                'plans.slug as plan_slug',
                DB::raw('COALESCE(time_entries_30d_metrics.time_entries_30d, 0) as time_entries_30d'),
                DB::raw('activity_metrics.last_activity_at as last_activity_at'),
            ])
            ->selectRaw(
                'CASE WHEN companies.is_blocked THEN 60 ELSE 0 END
                    + CASE WHEN subscriptions.status = ? THEN 50 ELSE 0 END
                    + CASE
                        WHEN activity_metrics.last_activity_at IS NULL THEN 30
                        WHEN activity_metrics.last_activity_at < ? THEN 25
                        ELSE 0
                    END as risk_score',
                [SubscriptionStatus::PAST_DUE->value, $day14]
            )
            ->orderByDesc('risk_score')
            ->orderBy('activity_metrics.last_activity_at')
            ->limit($limit)
            ->get()
            ->filter(fn ($row) => (int) $row->risk_score > 0)
            ->values()
            ->map(function ($row) use ($now, $day14) {
                return [
                    'company' => $this->companySummary($row->id, $row->name, $row->slug),
                    'subscription_status' => $row->subscription_status,
                    'plan_slug' => $row->plan_slug,
                    'health_status' => $this->healthStatus($row, $day14),
                    'risk_score' => (int) $row->risk_score,
                    'risk_reasons' => $this->riskReasons($row, $day14),
                    'time_entries_30d' => (int) $row->time_entries_30d,
                    'last_activity_at' => $this->toIsoString($row->last_activity_at),
                    'days_inactive' => $row->last_activity_at ? Carbon::parse($row->last_activity_at)->diffInDays($now) : null,
                ];
            })
            ->all();
    }

    protected function riskReasons(object $company, string $activeThreshold): array
    {
        $reasons = [];

        if ($company->is_blocked) {
            $reasons[] = 'blocked';
        }

        if ($company->subscription_status === SubscriptionStatus::PAST_DUE->value) {
            $reasons[] = 'past_due';
        }

        if (empty($company->last_activity_at)) {
            $reasons[] = 'no_activity';
        } elseif ($company->last_activity_at < $activeThreshold) {
            $reasons[] = 'inactive_14d';
        }

        return $reasons;
    }

    protected function companySummary(?string $id, ?string $name, ?string $slug): ?array
    {
        if (! $id) {
            return null;
        }

        return [
            'id' => $id,
            'name' => $name,
            'slug' => $slug,
        ];
    }

    protected function toIsoString(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toISOString() : null;
    }

    protected function estimatedRevenueSnapshot(): array
    {
        $activeSubscriptions = DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereNull('subscriptions.deleted_at')
            ->where('subscriptions.status', SubscriptionStatus::ACTIVE->value)
            ->select('plans.price_cents', 'plans.billing_interval')
            ->get();

        $mrrCents = $activeSubscriptions->sum(function ($row) {
            return match ($row->billing_interval) {
                'month' => (int) $row->price_cents,
                'year' => (int) round(((int) $row->price_cents) / 12),
                default => 0,
            };
        });

        $activeCount = $activeSubscriptions->count();
        $avgTicketCents = $activeCount > 0 ? (int) round($mrrCents / $activeCount) : 0;

        return [
            'estimated_mrr_cents' => $mrrCents,
            'estimated_mrr' => round($mrrCents / 100, 2),
            'average_ticket_cents' => $avgTicketCents,
            'average_ticket' => round($avgTicketCents / 100, 2),
            'currency' => 'EUR',
        ];
    }

    protected function employeesCountSubquery()
    {
        return DB::table('users')
            ->selectRaw('company_id, COUNT(*) as employees_count')
            ->whereNotNull('company_id')
            ->groupBy('company_id');
    }

    protected function activeEmployeesSubquery(string $from)
    {
        return DB::table('time_entries')
            ->selectRaw('company_id, COUNT(DISTINCT user_id) as active_employees_30d')
            ->where('clocked_at', '>=', $from)
            ->groupBy('company_id');
    }

    protected function timeEntriesTodaySubquery(string $from)
    {
        return DB::table('time_entries')
            ->selectRaw('company_id, COUNT(*) as time_entries_today')
            ->where('clocked_at', '>=', $from)
            ->groupBy('company_id');
    }

    protected function timeEntries30dSubquery(string $from)
    {
        return DB::table('time_entries')
            ->selectRaw('company_id, COUNT(*) as time_entries_30d')
            ->where('clocked_at', '>=', $from)
            ->groupBy('company_id');
    }

    protected function lastActivitySubquery()
    {
        return DB::table('time_entries')
            ->selectRaw('company_id, MAX(clocked_at) as last_activity_at')
            ->groupBy('company_id');
    }
}
