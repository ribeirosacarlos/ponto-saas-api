<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(CommercialSchema::table('email_sequence_steps'), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('sequence_id');
            $table->uuid('template_id');
            $table->unsignedInteger('position');
            $table->string('name')->nullable();
            $table->unsignedInteger('delay_days')->default(0);
            $table->time('send_time_override')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('sequence_id')
                ->references('id')->on(CommercialSchema::table('email_sequences'))
                ->restrictOnDelete();
            $table->foreign('template_id')
                ->references('id')->on(CommercialSchema::table('email_templates'))
                ->restrictOnDelete();

            $table->unique(['sequence_id', 'position']);
            $table->index('sequence_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('email_sequence_steps'));
    }
};
