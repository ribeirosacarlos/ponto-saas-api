<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Admin</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-950 text-stone-100">
    <div class="relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(251,191,36,0.18),_transparent_28%),radial-gradient(circle_at_top_right,_rgba(34,197,94,0.14),_transparent_24%),linear-gradient(180deg,_rgba(12,10,9,1)_0%,_rgba(28,25,23,1)_100%)]"></div>
        <div class="relative mx-auto max-w-7xl px-6 py-10 lg:px-10">
            <div class="flex flex-col gap-4 border-b border-white/10 pb-8 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm uppercase tracking-[0.35em] text-amber-300/80">Platform Control</p>
                    <h1 class="mt-3 text-4xl font-semibold tracking-tight text-white">Super Admin</h1>
                    <p class="mt-3 max-w-3xl text-sm text-stone-300">
                        Visão consolidada do SaaS com foco em uso, receita, risco e saúde operacional por empresa.
                    </p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-stone-300 backdrop-blur">
                    Atualizado em {{ data_get($summary, 'generated_at') }}
                </div>
            </div>

            <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-3xl border border-white/10 bg-white/6 p-5 backdrop-blur">
                    <p class="text-xs uppercase tracking-[0.3em] text-stone-400">Companies</p>
                    <p class="mt-4 text-4xl font-semibold">{{ data_get($summary, 'companies.total', 0) }}</p>
                    <p class="mt-2 text-sm text-stone-300">{{ data_get($summary, 'companies.active_last_30_days', 0) }} ativas em 30 dias</p>
                </div>
                <div class="rounded-3xl border border-emerald-400/20 bg-emerald-400/8 p-5 backdrop-blur">
                    <p class="text-xs uppercase tracking-[0.3em] text-emerald-200/80">Receita</p>
                    <p class="mt-4 text-4xl font-semibold">€{{ number_format((float) data_get($summary, 'revenue.estimated_mrr', 0), 2, ',', '.') }}</p>
                    <p class="mt-2 text-sm text-emerald-100/80">MRR estimado</p>
                </div>
                <div class="rounded-3xl border border-sky-400/20 bg-sky-400/8 p-5 backdrop-blur">
                    <p class="text-xs uppercase tracking-[0.3em] text-sky-200/80">Colaboradores</p>
                    <p class="mt-4 text-4xl font-semibold">{{ data_get($summary, 'employees.total', 0) }}</p>
                    <p class="mt-2 text-sm text-sky-100/80">{{ data_get($summary, 'employees.active_last_30_days', 0) }} ativos em 30 dias</p>
                </div>
                <div class="rounded-3xl border border-rose-400/20 bg-rose-400/8 p-5 backdrop-blur">
                    <p class="text-xs uppercase tracking-[0.3em] text-rose-200/80">Risco</p>
                    <p class="mt-4 text-4xl font-semibold">{{ data_get($summary, 'companies.at_risk', 0) }}</p>
                    <p class="mt-2 text-sm text-rose-100/80">empresas em atenção</p>
                </div>
            </div>

            <div class="mt-8 grid gap-4 lg:grid-cols-3">
                <div class="rounded-3xl border border-white/10 bg-black/20 p-6">
                    <h2 class="text-lg font-medium text-white">Assinaturas</h2>
                    <dl class="mt-5 space-y-3 text-sm text-stone-300">
                        <div class="flex items-center justify-between"><dt>Trial</dt><dd>{{ data_get($summary, 'companies.trialing', 0) }}</dd></div>
                        <div class="flex items-center justify-between"><dt>Pagantes</dt><dd>{{ data_get($summary, 'companies.paying', 0) }}</dd></div>
                        <div class="flex items-center justify-between"><dt>Inadimplentes</dt><dd>{{ data_get($summary, 'companies.past_due', 0) }}</dd></div>
                        <div class="flex items-center justify-between"><dt>Vencendo em 7 dias</dt><dd>{{ data_get($summary, 'companies.expiring_in_7_days', 0) }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-3xl border border-white/10 bg-black/20 p-6">
                    <h2 class="text-lg font-medium text-white">Operação</h2>
                    <dl class="mt-5 space-y-3 text-sm text-stone-300">
                        <div class="flex items-center justify-between"><dt>Pontos hoje</dt><dd>{{ data_get($summary, 'time_entries.today', 0) }}</dd></div>
                        <div class="flex items-center justify-between"><dt>Pontos 7 dias</dt><dd>{{ data_get($summary, 'time_entries.last_7_days', 0) }}</dd></div>
                        <div class="flex items-center justify-between"><dt>Pontos 30 dias</dt><dd>{{ data_get($summary, 'time_entries.last_30_days', 0) }}</dd></div>
                        <div class="flex items-center justify-between"><dt>Novas empresas 30 dias</dt><dd>{{ data_get($summary, 'companies.new_last_30_days', 0) }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-3xl border border-white/10 bg-black/20 p-6">
                    <h2 class="text-lg font-medium text-white">Qualidade da base</h2>
                    <dl class="mt-5 space-y-3 text-sm text-stone-300">
                        <div class="flex items-center justify-between"><dt>Bloqueadas</dt><dd>{{ data_get($summary, 'companies.blocked', 0) }}</dd></div>
                        <div class="flex items-center justify-between"><dt>Ativas 7 dias</dt><dd>{{ data_get($summary, 'companies.active_last_7_days', 0) }}</dd></div>
                        <div class="flex items-center justify-between"><dt>Ativas 30 dias</dt><dd>{{ data_get($summary, 'companies.active_last_30_days', 0) }}</dd></div>
                        <div class="flex items-center justify-between"><dt>Ticket médio</dt><dd>€{{ number_format((float) data_get($summary, 'revenue.average_ticket', 0), 2, ',', '.') }}</dd></div>
                    </dl>
                </div>
            </div>

            <div class="mt-8 rounded-3xl border border-white/10 bg-black/25 p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-medium text-white">Empresas</h2>
                        <p class="mt-1 text-sm text-stone-400">Amostra inicial com métricas básicas para operação e suporte.</p>
                    </div>
                    <div class="text-sm text-stone-400">Top 10 por atividade recente</div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/10 text-left text-sm">
                        <thead class="text-xs uppercase tracking-[0.22em] text-stone-500">
                            <tr>
                                <th class="pb-3">Empresa</th>
                                <th class="pb-3">Plano</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Colaboradores</th>
                                <th class="pb-3">Pontos 30d</th>
                                <th class="pb-3">Última atividade</th>
                                <th class="pb-3">Saúde</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 text-stone-200">
                            @foreach ($companies as $company)
                                <tr>
                                    <td class="py-4 pr-6">
                                        <div class="font-medium text-white">{{ $company->name }}</div>
                                        <div class="text-xs text-stone-500">{{ $company->slug }}</div>
                                    </td>
                                    <td class="py-4 pr-6">{{ $company->plan_name ?? 'Sem plano' }}</td>
                                    <td class="py-4 pr-6">{{ $company->subscription_status_label }}</td>
                                    <td class="py-4 pr-6">{{ $company->employees_count }} / {{ $company->active_employees_30d }}</td>
                                    <td class="py-4 pr-6">{{ $company->time_entries_30d }}</td>
                                    <td class="py-4 pr-6">{{ $company->last_activity_at ?? 'Sem atividade' }}</td>
                                    <td class="py-4">
                                        <span class="rounded-full px-3 py-1 text-xs font-medium
                                            @class([
                                                'bg-emerald-400/15 text-emerald-200' => $company->health_status === 'healthy',
                                                'bg-amber-400/15 text-amber-200' => $company->health_status === 'warning',
                                                'bg-rose-400/15 text-rose-200' => $company->health_status === 'critical',
                                            ])">
                                            {{ strtoupper($company->health_status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
