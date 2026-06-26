<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $roles = [
        'commercial_manager' => 'Commercial Manager',
        'commercial_agent' => 'Commercial Agent',
    ];

    public function up(): void
    {
        foreach ($this->roles as $name => $displayName) {
            $existing = DB::table('roles')->where('name', $name)->first();

            if ($existing) {
                DB::table('roles')->where('name', $name)->update([
                    'display_name' => $displayName,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('roles')->insert([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'display_name' => $displayName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->whereIn('name', array_keys($this->roles))->delete();
    }
};
