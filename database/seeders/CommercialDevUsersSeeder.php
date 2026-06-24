<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Usuários fictícios commercial_manager/commercial_agent para ambiente local/dev.
 * Nunca deve rodar em produção.
 */
class CommercialDevUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $roles = Role::whereIn('name', ['commercial_manager', 'commercial_agent'])
            ->get()
            ->keyBy('name');

        $users = [
            ['name' => 'Mariana Comercial (Manager)', 'email' => 'comercial.manager@jornafy.dev', 'role' => 'commercial_manager'],
            ['name' => 'Tiago Comercial (Agent)', 'email' => 'comercial.agent@jornafy.dev', 'role' => 'commercial_agent'],
        ];

        foreach ($users as $data) {
            if (! isset($roles[$data['role']])) {
                continue;
            }

            $user = User::firstOrNew(['email' => $data['email']]);

            if (! $user->exists) {
                $user->id = (string) Str::uuid();
            }

            $user->company_id = null;
            $user->name = $data['name'];
            $user->password = Hash::make('123456');
            $user->save();

            $user->syncRoles([$data['role']]);
        }
    }
}
