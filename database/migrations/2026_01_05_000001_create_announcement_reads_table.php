<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->uuid('announcement_id');
            $table->foreign('announcement_id')->references('id')->on('announcements')->onDelete('cascade');

            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamp('seen_at')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'announcement_id', 'user_id'], 'announcement_read_unique');

            $table->index('company_id');
            $table->index('announcement_id');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
    }
};
