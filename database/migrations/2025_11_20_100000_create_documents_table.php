<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->string('title', 180);
            $table->string('category');
            $table->string('status')->default('pending');
            $table->string('mime_type', 191);
            $table->string('ext', 10);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('path');
            $table->string('storage_disk')->default('local');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('user_id');
            $table->index(['company_id', 'user_id', 'status', 'updated_at']);

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
