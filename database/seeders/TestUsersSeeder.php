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
        User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $adminRole->id,
            'name' => 'Admin Teste',
            'email' => 'admin@teste.com',
            'password' => bcrypt('123456'),
        ]);

        // Manager
        User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $managerRole->id,
            'name' => 'Manager Teste',
            'email' => 'manager@teste.com',
            'password' => bcrypt('123456'),
        ]);

        // Area Manager
        User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $areaManagerRole->id,
            'name' => 'Area Manager Teste',
            'email' => 'area_manager@teste.com',
            'password' => bcrypt('123456'),
        ]);

        // Employees
        User::factory(5)->create([
            'company_id' => $company->id,
            'role_id' => $employeeRole->id,
            'password' => bcrypt('123456'),
        ]);
    }
}
