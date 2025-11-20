<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Adjustment;
use Illuminate\Support\Str;

class AdjustmentSeeder extends Seeder
{
    public function run()
    {
        // Buscar employees
        $employees = User::whereHas('roles', function($q) {
            $q->where('name', 'employee');
        })->get();

        // Buscar area manager
        $manager = User::whereHas('roles', function($q) {
            $q->where('name', 'area_manager');
        })->first();

        foreach ($employees as $emp) {

            // Ajuste pendente
            Adjustment::create([
                'id' => Str::uuid(),
                'company_id' => $emp->company_id,
                'user_id' => $emp->id,
                'original_time' => now()->setTime(8, 0),
                'corrected_time' => now()->setTime(8, 10),
                'reason' => 'Esqueci de bater o ponto',
                'status' => 'pending',
            ]);

            // Ajuste aprovado
            Adjustment::create([
                'id' => Str::uuid(),
                'company_id' => $emp->company_id,
                'user_id' => $emp->id,
                'original_time' => now()->subDays(1)->setTime(8, 0),
                'corrected_time' => now()->subDays(1)->setTime(8, 5),
                'reason' => 'Atraso justificado',
                'status' => 'approved',
                'approver_id' => $manager->id,
            ]);

            // Ajuste rejeitado
            Adjustment::create([
                'id' => Str::uuid(),
                'company_id' => $emp->company_id,
                'user_id' => $emp->id,
                'original_time' => now()->subDays(2)->setTime(18, 0),
                'corrected_time' => now()->subDays(2)->setTime(18, 15),
                'reason' => 'Erro de digitação',
                'status' => 'rejected',
                'approver_id' => $manager->id,
            ]);
        }
    }
}
