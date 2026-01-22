<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('document_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->uuid('document_id');
            $table->string('type');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_notifications');
    }
};
