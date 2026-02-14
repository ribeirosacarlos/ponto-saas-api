<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->string('event_kind', 32)->nullable()->after('type');
            $table->uuid('user_shift_id')->nullable()->after('user_id');

            $table->foreign('user_shift_id')->references('id')->on('user_shifts')->nullOnDelete();
            $table->index(['company_id', 'user_id', 'clocked_at'], 'time_entries_company_user_clocked_idx');
            $table->index('user_shift_id');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropForeign(['user_shift_id']);
            $table->dropIndex('time_entries_company_user_clocked_idx');
            $table->dropIndex(['user_shift_id']);
            $table->dropColumn(['event_kind', 'user_shift_id']);
        });
    }
};
