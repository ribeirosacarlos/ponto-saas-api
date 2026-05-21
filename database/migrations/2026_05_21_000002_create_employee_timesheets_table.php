<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_timesheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('monthly_closure_id');
            $table->uuid('employee_id');
            $table->string('status', 20)->default('pending_employee');
            $table->json('snapshot')->nullable();
            $table->timestamp('snapshot_generated_at')->nullable();
            $table->string('pdf_path', 500)->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['monthly_closure_id', 'employee_id']);
            $table->index(['company_id', 'employee_id']);
            $table->index(['monthly_closure_id', 'status']);

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('monthly_closure_id')->references('id')->on('monthly_closures')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_timesheets');
    }
};
