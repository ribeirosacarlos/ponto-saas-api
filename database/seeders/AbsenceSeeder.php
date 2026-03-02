<?php

namespace Database\Seeders;

use App\Models\Absence;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AbsenceSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }

        $preferredUser = User::where('email', 'edudtk7@gmail.com')
            ->orWhere('name', 'EDUARDO CHEFE')
            ->first();

        $employee = User::where('company_id', $company->id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'employee');
            })
            ->first();

        $targetUser = $preferredUser ?: $employee;

        if (! $targetUser) {
            return;
        }

        $today = Carbon::today();

        Absence::firstOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $targetUser->id,
                'type' => 'ATESTADO_MEDICO',
                'start_date' => $today->copy()->subDays(12)->toDateString(),
                'end_date' => $today->copy()->subDays(10)->toDateString(),
            ],
            [
                'id' => (string) Str::uuid(),
                'status' => 'recorded',
                'comment' => 'Teste de ausencia (atestado medico).',
                'counts_for_accrual' => true,
                'created_by' => $targetUser->id,
            ]
        );

        Absence::firstOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $targetUser->id,
                'type' => 'ASSUNTOS_PESSOAIS',
                'start_date' => $today->copy()->subDays(5)->toDateString(),
                'end_date' => $today->copy()->subDays(5)->toDateString(),
            ],
            [
                'id' => (string) Str::uuid(),
                'status' => 'recorded',
                'comment' => 'Teste de ausencia (assuntos pessoais).',
                'counts_for_accrual' => true,
                'created_by' => $targetUser->id,
            ]
        );

        Absence::firstOrCreate(
            [
                'company_id' => $company->id,
                'user_id' => $targetUser->id,
                'type' => 'FALTA_NAO_JUSTIFICADA',
                'start_date' => $today->copy()->subDays(2)->toDateString(),
                'end_date' => $today->copy()->subDays(2)->toDateString(),
            ],
            [
                'id' => (string) Str::uuid(),
                'status' => 'recorded',
                'comment' => 'Mock de ausencia para validar desconto no saldo.',
                'counts_for_accrual' => false,
                'created_by' => $targetUser->id,
            ]
        );
    }
}
