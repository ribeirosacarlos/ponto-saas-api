<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('roles')->where('name', 'super_admin')->first();

        if ($existing) {
            DB::table('roles')
                ->where('name', 'super_admin')
                ->update([
                    'display_name' => 'Platform Administrator',
                    'updated_at' => now(),
                ]);

            return;
        }

        DB::table('roles')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'super_admin',
            'display_name' => 'Platform Administrator',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'super_admin')->delete();
    }
};
