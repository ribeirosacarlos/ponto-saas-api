<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamp('clocked_at');
            $table->enum('type', ['in','out']);

            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('source')->nullable(); // web/mobile

            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
