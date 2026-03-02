<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_policies', 'annual_entitlement_days')) {
                $table->decimal('annual_entitlement_days', 6, 2)->default(30.00)->after('days_per_year');
            }

            if (! Schema::hasColumn('leave_policies', 'accrual_basis')) {
                $table->string('accrual_basis')->default('calendar_days')->after('annual_entitlement_days');
            }

            if (! Schema::hasColumn('leave_policies', 'day_work_threshold_minutes')) {
                $table->unsignedInteger('day_work_threshold_minutes')->default(1)->after('accrual_basis');
            }
        });

        if (Schema::hasColumn('leave_policies', 'annual_entitlement_days')) {
            DB::statement('UPDATE leave_policies SET annual_entitlement_days = days_per_year WHERE annual_entitlement_days IS NULL');
        }

        if (Schema::hasColumn('leave_policies', 'accrual_basis')) {
            DB::statement("UPDATE leave_policies SET accrual_basis = 'calendar_days' WHERE accrual_basis IS NULL");
        }

        if (Schema::hasColumn('leave_policies', 'day_work_threshold_minutes')) {
            DB::statement('UPDATE leave_policies SET day_work_threshold_minutes = 1 WHERE day_work_threshold_minutes IS NULL');
        }
    }

    public function down(): void
    {
        Schema::table('leave_policies', function (Blueprint $table) {
            if (Schema::hasColumn('leave_policies', 'day_work_threshold_minutes')) {
                $table->dropColumn('day_work_threshold_minutes');
            }

            if (Schema::hasColumn('leave_policies', 'accrual_basis')) {
                $table->dropColumn('accrual_basis');
            }

            if (Schema::hasColumn('leave_policies', 'annual_entitlement_days')) {
                $table->dropColumn('annual_entitlement_days');
            }
        });
    }
};
