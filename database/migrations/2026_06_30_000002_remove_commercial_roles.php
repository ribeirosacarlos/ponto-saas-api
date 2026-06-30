<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roles = DB::table('roles')
            ->whereIn('name', ['commercial_manager', 'commercial_agent'])
            ->get();

        foreach ($roles as $role) {
            DB::table('role_user')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }
    }

    public function down(): void
    {
        $now = now();

        $manager = DB::table('roles')->insertGetId([
            'id'           => \Illuminate\Support\Str::uuid(),
            'name'         => 'commercial_manager',
            'display_name' => 'Commercial Manager',
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        DB::table('roles')->insert([
            'id'           => \Illuminate\Support\Str::uuid(),
            'name'         => 'commercial_agent',
            'display_name' => 'Commercial Agent',
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }
};
