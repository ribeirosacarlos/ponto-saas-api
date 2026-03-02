<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absences', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->string('type');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('recorded');
            $table->text('comment')->nullable();

            $table->uuid('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'user_id']);
            $table->index(['company_id', 'start_date']);
            $table->index(['company_id', 'user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absences');
    }
};
