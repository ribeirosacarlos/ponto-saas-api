<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RolesTableSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['name' => 'super_admin', 'display_name' => 'Platform Administrator'],
            ['name' => 'admin', 'display_name' => 'Administrator'],
            ['name' => 'manager', 'display_name' => 'Manager'],
            ['name' => 'area_manager', 'display_name' => 'Area Manager'],
            ['name' => 'employee', 'display_name' => 'Employee'],
        ];

        foreach ($roles as $r) {
            Role::updateOrCreate(
                ['name' => $r['name']],
                ['display_name' => $r['display_name']]
            );
        }        
    }
}
