<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\TimeEntry;

class TimeEntrySeeder extends Seeder
{
    public function run()
    {
        // Buscar apenas usuários com role employee
        $employees = User::whereHas('roles', function ($q) {
            $q->where('name', 'employee');
        })->get();

        foreach ($employees as $emp) {

            // Entrada
            TimeEntry::create([
                'user_id' => $emp->id,
                'clocked_at' => now()->setTime(8, 0),
                'type' => 'in',
                'latitude' => '-23.550520',
                'longitude' => '-46.633308',
                'source' => 'web',
            ]);

            // Saída
            TimeEntry::create([
                'user_id' => $emp->id,
                'clocked_at' => now()->setTime(17, 0),
                'type' => 'out',
                'latitude' => '-23.550520',
                'longitude' => '-46.633308',
                'source' => 'web',
            ]);
        }
    }
}
