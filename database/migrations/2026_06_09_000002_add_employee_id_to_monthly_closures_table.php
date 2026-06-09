<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_closures', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'reference_year', 'reference_month']);
            $table->uuid('employee_id')->nullable()->after('closed_by');

            $table->foreign('employee_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(
                ['company_id', 'employee_id', 'reference_year', 'reference_month'],
                'monthly_closures_company_employee_period_unique'
            );
            $table->index(['company_id', 'employee_id'], 'monthly_closures_company_employee_idx');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_closures', function (Blueprint $table) {
            $table->dropUnique('monthly_closures_company_employee_period_unique');
            $table->dropIndex('monthly_closures_company_employee_idx');
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
            $table->unique(['company_id', 'reference_year', 'reference_month']);
        });
    }
};
