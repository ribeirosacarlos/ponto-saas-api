<?php

namespace App\Services\SuperAdmin;

use App\Enums\SubscriptionStatus;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SuperAdminAnalyticsService
{
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
