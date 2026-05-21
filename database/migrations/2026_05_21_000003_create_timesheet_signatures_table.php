<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timesheet_signatures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('employee_timesheet_id');
            $table->uuid('signer_id');
            $table->string('role', 20);
            $table->timestamp('signed_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1000)->nullable();

            $table->unique(['employee_timesheet_id', 'role']);
            $table->index(['company_id', 'signer_id']);

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('employee_timesheet_id')->references('id')->on('employee_timesheets')->cascadeOnDelete();
            $table->foreign('signer_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheet_signatures');
    }
};
