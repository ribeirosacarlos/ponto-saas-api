<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->softDeletes()->after('updated_at');
            $table->index(['company_id', 'deleted_at'], 'time_entries_company_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex('time_entries_company_deleted_at_idx');
            $table->dropSoftDeletes();
        });
    }
};
