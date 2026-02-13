<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('time_entries', 'adjustment_origin_id')) {
            Schema::table('time_entries', function (Blueprint $table) {
                $table->dropForeign(['adjustment_origin_id']);
                $table->dropColumn('adjustment_origin_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('time_entries', 'adjustment_origin_id')) {
            Schema::table('time_entries', function (Blueprint $table) {
                $table->uuid('adjustment_origin_id')->nullable()->after('adjustment_status');
                $table->foreign('adjustment_origin_id')->references('id')->on('time_entries')->nullOnDelete();
            });
        }
    }
};
