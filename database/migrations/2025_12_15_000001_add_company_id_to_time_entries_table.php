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
        Schema::table('time_entries', function (Blueprint $table) {
            $table->uuid('company_id')->nullable()->after('id');
        });

        // Backfill existing rows with the company id from the owning user
        $connection = Schema::getConnection();
        $driver = $connection->getConfig('driver');

        if ($driver === 'sqlite') {
            // sqlite cannot run ALTER COLUMN easily, so we just populate the column.
            DB::statement('UPDATE time_entries SET company_id = (SELECT company_id FROM users WHERE users.id = time_entries.user_id)');
        } else {
            // Postgres is the production database and supports ALTER COLUMN.
            DB::statement('UPDATE time_entries SET company_id = users.company_id FROM users WHERE time_entries.user_id = users.id');
            DB::statement('ALTER TABLE time_entries ALTER COLUMN company_id SET NOT NULL');
        }

        Schema::table('time_entries', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
