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

        Schema::create(CommercialSchema::table('lead_step_logs'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('step_id');
            $table->uuid('user_id');
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('lead_id')
                ->references('id')->on(CommercialSchema::table('leads'))
                ->cascadeOnDelete();
            $table->foreign('step_id')
                ->references('id')->on(CommercialSchema::table('lead_steps'));
            $table->foreign('user_id')
                ->references('id')->on(CommercialSchema::usersTable());

            $table->index(['lead_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('lead_step_logs'));
    }
};
