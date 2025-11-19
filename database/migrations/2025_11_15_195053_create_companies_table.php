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
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Informações básicas
            $table->string('name');
            $table->string('slug')->unique(); // usado para subdomínio: empresa.ponto.com

            $table->string('document')->nullable(); // CNPJ ou CPF
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();

            // Configurações de plano para SaaS
            $table->string('plan')->default('free'); // free / pro / enterprise
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
