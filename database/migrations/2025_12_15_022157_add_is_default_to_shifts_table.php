<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_flexible');
        });

        DB::statement('CREATE UNIQUE INDEX shifts_company_default_unique ON shifts (company_id) WHERE is_default = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS shifts_company_default_unique');

        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
