<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;

        $holidays = [
            '01-01' => 'Año Nuevo',
            '01-06' => 'Epifanía del Señor (Reyes)',
            '05-01' => 'Fiesta del Trabajo',
            '08-15' => 'Asunción de la Virgen',
            '10-12' => 'Fiesta Nacional de España',
            '11-01' => 'Todos los Santos',
            '12-06' => 'Día de la Constitución Española',
            '12-08' => 'Inmaculada Concepción',
            '12-25' => 'Navidad',
        ];

        Company::all()->each(function ($company) use ($holidays, $year) {
            foreach ($holidays as $monthDay => $name) {
                Holiday::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'date'       => sprintf('%s-%s', $year, $monthDay),
                    ],
                    [
                        'name'  => $name,
                        'scope' => 'national',
                    ]
                );
            }
        });
    }
}
