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

        Schema::create(CommercialSchema::table('affiliate_clicks'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('affiliate_id');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->string('referer')->nullable();
            $table->string('landing_page')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->timestampTz('clicked_at');
            $table->timestamps();

            $table->foreign('affiliate_id')
                ->references('id')->on(CommercialSchema::table('affiliates'))
                ->cascadeOnDelete();

            $table->index(['affiliate_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('affiliate_clicks'));
    }
};
