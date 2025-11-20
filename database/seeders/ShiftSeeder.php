<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Shift;

class ShiftSeeder extends Seeder
{
    public function run()
    {
        $company = Company::first(); // Empresa Teste

        $shifts = [
            [
                'name' => 'Turno Normal',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'is_flexible' => false,
            ],
            [
                'name' => 'Turno Manhã',
                'start_time' => '06:00',
                'end_time' => '14:00',
                'is_flexible' => false,
            ],
            [
                'name' => 'Turno Tarde',
                'start_time' => '14:00',
                'end_time' => '22:00',
                'is_flexible' => false,
            ],
        ];

        foreach ($shifts as $s) {
            Shift::create([
                'company_id' => $company->id,
                ...$s
            ]);
        }
    }
}
