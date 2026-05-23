<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_closures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('closed_by');
            $table->smallInteger('reference_year');
            $table->tinyInteger('reference_month');
            $table->string('status', 20)->default('processing');
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->unique(['company_id', 'reference_year', 'reference_month']);
            $table->index(['company_id', 'status']);

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('closed_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_closures');
    }
};
