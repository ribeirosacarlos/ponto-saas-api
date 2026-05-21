<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_disputes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('employee_timesheet_id');
            $table->uuid('employee_id');
            $table->text('reason');
            $table->string('status', 20)->default('open');
            $table->text('resolution_note')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['employee_timesheet_id', 'status']);
            $table->index(['company_id', 'status']);

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('employee_timesheet_id')->references('id')->on('employee_timesheets')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_disputes');
    }
};
