<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'area_id')) {
                $table->uuid('area_id')->nullable()->after('company_id');
                $table->foreign('area_id')->references('id')->on('areas')->nullOnDelete();
                $table->index(['company_id', 'area_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'area_id')) {
                $table->dropForeign(['area_id']);
                $table->dropIndex(['company_id', 'area_id']);
                $table->dropColumn('area_id');
            }
        });
    }
};
