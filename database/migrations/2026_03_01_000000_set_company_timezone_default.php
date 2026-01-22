<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->whereNull('timezone')->update(['timezone' => 'Europe/Madrid']);

        $driver = DB::getDriverName();

        if (! in_array($driver, ['sqlite'], true)) {
            DB::statement("ALTER TABLE companies ALTER COLUMN timezone SET DEFAULT 'Europe/Madrid'");
            DB::statement("ALTER TABLE companies ALTER COLUMN timezone SET NOT NULL");
        }

        Schema::table('companies', function (Blueprint $table) {
            $table->index('timezone');
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS companies_timezone_index');

        $driver = DB::getDriverName();

        if (! in_array($driver, ['sqlite'], true)) {
            DB::statement("ALTER TABLE companies ALTER COLUMN timezone DROP DEFAULT");
            DB::statement("ALTER TABLE companies ALTER COLUMN timezone DROP NOT NULL");
        }
    }
};
