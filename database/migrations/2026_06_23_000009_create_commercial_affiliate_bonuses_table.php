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

        Schema::create(CommercialSchema::table('affiliate_bonuses'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('affiliate_id');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('clients_count')->default(0);
            $table->unsignedInteger('bonus_every_clients');
            $table->decimal('bonus_amount', 10, 2);
            $table->decimal('total_bonus_amount', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestampTz('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('affiliate_id')
                ->references('id')->on(CommercialSchema::table('affiliates'))
                ->cascadeOnDelete();

            $table->unique(['affiliate_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('affiliate_bonuses'));
    }
};
