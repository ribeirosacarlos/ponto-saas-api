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

        Schema::create(CommercialSchema::table('commission_plans'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('commission_type');
            $table->decimal('commission_percentage', 5, 2);
            $table->unsignedInteger('recurrence_months');
            $table->boolean('bonus_enabled')->default(false);
            $table->unsignedInteger('bonus_every_clients')->nullable();
            $table->decimal('bonus_amount', 10, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('commission_plans'));
    }
};
