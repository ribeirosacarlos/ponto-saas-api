<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            if (! Schema::hasColumn('absences', 'coverage_type')) {
                $table->string('coverage_type', 20)->default('full_day')->after('type');
            }

            if (! Schema::hasColumn('absences', 'start_time')) {
                $table->time('start_time')->nullable()->after('end_date');
            }

            if (! Schema::hasColumn('absences', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }

            if (! Schema::hasColumn('absences', 'approved_by')) {
                $table->uuid('approved_by')->nullable()->after('created_by');
                $table->timestamp('approved_at')->nullable()->after('approved_by');
                $table->uuid('rejected_by')->nullable()->after('approved_at');
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
                $table->text('rejection_reason')->nullable()->after('rejected_at');
                $table->uuid('canceled_by')->nullable()->after('rejection_reason');
                $table->timestamp('canceled_at')->nullable()->after('canceled_by');

                $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('canceled_by')->references('id')->on('users')->nullOnDelete();
            }

            $table->index(['company_id', 'user_id', 'type', 'status'], 'absences_company_user_type_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->dropIndex('absences_company_user_type_status_idx');

            if (Schema::hasColumn('absences', 'approved_by')) {
                $table->dropForeign(['approved_by']);
                $table->dropForeign(['rejected_by']);
                $table->dropForeign(['canceled_by']);
                $table->dropColumn([
                    'approved_by',
                    'approved_at',
                    'rejected_by',
                    'rejected_at',
                    'rejection_reason',
                    'canceled_by',
                    'canceled_at',
                ]);
            }

            if (Schema::hasColumn('absences', 'end_time')) {
                $table->dropColumn('end_time');
            }

            if (Schema::hasColumn('absences', 'start_time')) {
                $table->dropColumn('start_time');
            }

            if (Schema::hasColumn('absences', 'coverage_type')) {
                $table->dropColumn('coverage_type');
            }
        });
    }
};
