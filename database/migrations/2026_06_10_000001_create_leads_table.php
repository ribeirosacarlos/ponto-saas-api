<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('email');
            $table->string('lead_magnet_type', 50);
            $table->string('page_slug')->nullable();
            $table->timestampTz('consented_at');
            $table->string('ip_hash', 64)->nullable();

            $table->timestampsTz();

            $table->unique(['email', 'lead_magnet_type']);
            $table->index('lead_magnet_type');
            $table->index('page_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
