<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->uuid('absence_id')->nullable()->after('user_shift_id');

            $table->foreign('absence_id')->references('id')->on('absences')->nullOnDelete();
            $table->index(['company_id', 'user_id', 'absence_id'], 'time_entries_company_user_absence_idx');
            $table->unique(['absence_id', 'clocked_at', 'type'], 'time_entries_absence_clocked_type_unique');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropUnique('time_entries_absence_clocked_type_unique');
            $table->dropIndex('time_entries_company_user_absence_idx');
            $table->dropForeign(['absence_id']);
            $table->dropColumn('absence_id');
        });
    }
};
