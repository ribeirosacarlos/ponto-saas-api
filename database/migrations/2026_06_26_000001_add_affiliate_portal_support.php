<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(CommercialSchema::table('affiliates'), function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->after('id');
            $table->foreign('user_id')->references('id')->on(CommercialSchema::usersTable())->nullOnDelete();
        });

        $existing = DB::table('roles')->where('name', 'affiliate')->first();

        if (! $existing) {
            DB::table('roles')->insert([
                'id' => (string) Str::uuid(),
                'name' => 'affiliate',
                'display_name' => 'Affiliate',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table(CommercialSchema::table('affiliates'), function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        DB::table('roles')->where('name', 'affiliate')->delete();
    }
};
