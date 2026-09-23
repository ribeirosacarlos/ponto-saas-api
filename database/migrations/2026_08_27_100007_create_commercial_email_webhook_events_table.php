<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(CommercialSchema::table('email_webhook_events'), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('provider_event_id')->unique();
            $table->string('type');
            $table->json('payload_json');
            $table->timestampTz('processed_at')->nullable();
            $table->text('processing_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('email_webhook_events'));
    }
};
