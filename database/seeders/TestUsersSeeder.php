<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Criar empresa de teste
        $company = Company::factory()->create([
            'name' => 'Empresa Teste'
        ]);

        // Roles
        $adminRole = Role::where('name', 'admin')->first();
        $managerRole = Role::where('name', 'manager')->first();
        $areaManagerRole = Role::where('name', 'area_manager')->first();
        $employeeRole = Role::where('name', 'employee')->first();

        // Admin
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Admin Teste',
            'email' => 'admin@teste.com',
            'password' => bcrypt('123456'),
        ]);
        $admin->roles()->attach($adminRole->id);

        // Manager
        $manager = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Manager Teste',
            'email' => 'manager@teste.com',
            'password' => bcrypt('123456'),
        ]);
        $manager->roles()->attach($managerRole->id);

        // Area Manager
        $areaManager = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Area Manager Teste',
            'email' => 'area_manager@teste.com',
            'password' => bcrypt('123456'),
        ]);
        $areaManager->roles()->attach($areaManagerRole->id);

        // Employees
        $employees = User::factory(5)->create([
            'company_id' => $company->id,
            'password' => bcrypt('123456'),
        ]);

        foreach ($employees as $emp) {
            $emp->roles()->attach($employeeRole->id);
        }
    }
}
