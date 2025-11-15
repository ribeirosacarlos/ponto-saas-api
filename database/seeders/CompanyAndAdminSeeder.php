<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class CompanyAndAdminSeeder extends Seeder
{
    public function run()
    {
        $company = Company::firstOrCreate(['slug' => 'acme'], ['name' => 'Acme Ltda']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@acme.local'],
            [
                'name' => 'Acme Admin',
                'password' => Hash::make('password'),
                'company_id' => $company->id,
            ]
        );

        $role = Role::where('name','admin')->first();
        if ($role && !$admin->roles->contains($role->id)) {
            $admin->roles()->attach($role->id);
        }
    }
}
