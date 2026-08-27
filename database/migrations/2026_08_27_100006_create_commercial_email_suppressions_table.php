<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(CommercialSchema::table('email_suppressions'), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('email')->unique();
            $table->uuid('lead_id')->nullable();
            $table->string('reason');
            $table->string('source');
            $table->text('notes')->nullable();

            $table->uuid('suppressed_by_user_id')->nullable();
            $table->timestampTz('suppressed_at');

            $table->timestamps();

            $table->foreign('lead_id')
                ->references('id')->on(CommercialSchema::table('leads'))
                ->nullOnDelete();
            $table->foreign('suppressed_by_user_id')
                ->references('id')->on(CommercialSchema::usersTable())
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('email_suppressions'));
    }
};
