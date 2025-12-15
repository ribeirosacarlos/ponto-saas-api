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
        Schema::create('vacation_days', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->uuid('vacation_request_id');
            $table->foreign('vacation_request_id')->references('id')->on('vacation_requests')->onDelete('cascade');

            $table->date('date');
            $table->string('day_type')->default('full_day');

            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'date']);
            $table->index('vacation_request_id');
            $table->index(['company_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vacation_days');
    }
};
