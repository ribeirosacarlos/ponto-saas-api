<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_employee_charges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('plan_id')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('status')->default('pending');
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('stripe_checkout_session_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')->on('companies')
                ->onDelete('cascade');

            $table->foreign('plan_id')
                ->references('id')->on('plans')
                ->nullOnDelete();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_employee_charges');
    }
};
