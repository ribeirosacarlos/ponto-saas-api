<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_user_management', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('area_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['company_id', 'area_id', 'user_id'], 'area_user_management_unique');
            $table->index(['company_id', 'area_id']);
            $table->index(['company_id', 'user_id']);
            $table->index(['area_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_user_management');
    }
};
