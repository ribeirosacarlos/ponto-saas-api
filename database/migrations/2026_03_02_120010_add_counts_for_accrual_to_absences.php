<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            if (! Schema::hasColumn('absences', 'counts_for_accrual')) {
                $table->boolean('counts_for_accrual')->default(true)->after('comment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            if (Schema::hasColumn('absences', 'counts_for_accrual')) {
                $table->dropColumn('counts_for_accrual');
            }
        });
    }
};
