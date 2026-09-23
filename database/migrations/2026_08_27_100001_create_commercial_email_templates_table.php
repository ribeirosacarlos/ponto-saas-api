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

        Schema::create(CommercialSchema::table('email_templates'), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->string('subject');
            $table->text('body_html');
            $table->text('body_text')->nullable();
            $table->json('available_variables')->nullable();
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by_user_id')->nullable();

            $table->timestamps();

            $table->foreign('created_by_user_id')
                ->references('id')->on(CommercialSchema::usersTable())
                ->nullOnDelete();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('email_templates'));
    }
};
