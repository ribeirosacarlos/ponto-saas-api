<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->string('adjustment_status')->nullable()->after('source');
            $table->text('adjustment_reason')->nullable()->after('adjustment_status');
            $table->uuid('adjustment_requested_by')->nullable()->after('adjustment_reason');
            $table->timestamp('adjustment_requested_at')->nullable()->after('adjustment_requested_by');
            $table->timestamp('proposed_clocked_at')->nullable()->after('adjustment_requested_at');
            $table->string('proposed_type')->nullable()->after('proposed_clocked_at');
            $table->decimal('proposed_latitude', 10, 6)->nullable()->after('proposed_type');
            $table->decimal('proposed_longitude', 10, 6)->nullable()->after('proposed_latitude');
            $table->string('proposed_source')->nullable()->after('proposed_longitude');
            $table->uuid('adjustment_reviewed_by')->nullable()->after('proposed_source');
            $table->timestamp('adjustment_reviewed_at')->nullable()->after('adjustment_reviewed_by');
            $table->text('adjustment_review_reason')->nullable()->after('adjustment_reviewed_at');

            $table->index(['company_id', 'adjustment_status'], 'time_entries_company_adjustment_status_idx');
            $table->index(['company_id', 'user_id', 'adjustment_status'], 'time_entries_company_user_adjustment_idx');

            $table->foreign('adjustment_requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('adjustment_reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropForeign(['adjustment_requested_by']);
            $table->dropForeign(['adjustment_reviewed_by']);
            $table->dropIndex('time_entries_company_adjustment_status_idx');
            $table->dropIndex('time_entries_company_user_adjustment_idx');
            $table->dropColumn([
                'adjustment_status',
                'adjustment_reason',
                'adjustment_requested_by',
                'adjustment_requested_at',
                'proposed_clocked_at',
                'proposed_type',
                'proposed_latitude',
                'proposed_longitude',
                'proposed_source',
                'adjustment_reviewed_by',
                'adjustment_reviewed_at',
                'adjustment_review_reason',
            ]);
        });
    }
};
