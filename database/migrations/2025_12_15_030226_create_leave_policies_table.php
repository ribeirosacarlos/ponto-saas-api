<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->string('name');
            $table->decimal('days_per_year', 6, 2)->default(30.00);
            $table->decimal('accrual_rate_per_month', 6, 3)->default(2.500);
            $table->string('counting_method')->default('calendar_days');
            $table->boolean('allow_carry_over')->default(false);
            $table->decimal('carry_over_limit_days', 6, 2)->nullable();
            $table->date('effective_from')->nullable();

            $table->timestamps();

            $table->index('company_id');
            $table->unique(['company_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
    }
};
