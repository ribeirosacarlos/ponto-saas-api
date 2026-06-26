<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        CommercialSchema::ensureSchemaExists();

        Schema::create(CommercialSchema::table('affiliates'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('slug')->unique();
            $table->uuid('commission_plan_id')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('commission_plan_id')
                ->references('id')->on(CommercialSchema::table('commission_plans'))
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('affiliates'));
    }
};
