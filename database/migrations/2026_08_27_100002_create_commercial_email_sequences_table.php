<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(CommercialSchema::table('email_sequences'), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('timezone')->nullable();

            $table->uuid('created_by_user_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by_user_id')
                ->references('id')->on(CommercialSchema::usersTable())
                ->nullOnDelete();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('email_sequences'));
    }
};
