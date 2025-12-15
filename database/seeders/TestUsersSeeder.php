<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use App\Services\UserShiftService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        if (! $company) {
            $this->call(CompanySeeder::class);
            $company = Company::first();
        }

        if (! $company) {
            return;
        }

        $roles = Role::whereIn('name', ['admin', 'manager', 'area_manager', 'employee'])
            ->get()
            ->keyBy('name');

        $shiftMap = Shift::where('company_id', $company->id)->get()->keyBy('name');

        $users = [
            ['name' => 'Ana Pereira', 'email' => 'ana.pereira@empresa.com', 'role' => 'admin', 'shift' => 'Jornada Padrão (Seg–Sex)'],
            ['name' => 'Bruno Carvalho', 'email' => 'bruno.carvalho@empresa.com', 'role' => 'manager', 'shift' => 'Turno Manhã'],
            ['name' => 'Carla Nunes', 'email' => 'carla.nunes@empresa.com', 'role' => 'area_manager', 'shift' => 'Jornada Padrão (Seg–Sex)'],
            ['name' => 'Diego Martins', 'email' => 'diego.martins@empresa.com', 'role' => 'employee', 'shift' => 'Turno Manhã'],
            ['name' => 'Eduarda Farias', 'email' => 'eduarda.farias@empresa.com', 'role' => 'employee', 'shift' => 'Jornada Padrão (Seg–Sex)'],
            ['name' => 'Felipe Moreira', 'email' => 'felipe.moreira@empresa.com', 'role' => 'employee', 'shift' => 'Jornada Padrão (Seg–Sex)'],
            ['name' => 'Gabriela Silva', 'email' => 'gabriela.silva@empresa.com', 'role' => 'employee', 'shift' => 'Turno Manhã'],
        ];

        /** @var UserShiftService $userShiftService */
        $userShiftService = app(UserShiftService::class);
        $defaultPassword = Hash::make('123456');

        foreach ($users as $data) {
            $user = User::firstOrNew(['email' => $data['email']]);

            if (! $user->exists) {
                $user->id = (string) Str::uuid();
            }

            $user->company_id = $company->id;
            $user->name = $data['name'];
            $user->password = $defaultPassword;
            $user->save();

            if (isset($roles[$data['role']])) {
                $user->syncRoles([$data['role']]);
            }

            $shift = $shiftMap[$data['shift']] ?? null;

            if ($shift) {
                $userShiftService->assign($user, $shift);
            } else {
                $userShiftService->assignDefaultIfAvailable($user);
            }
        }
    }
}
