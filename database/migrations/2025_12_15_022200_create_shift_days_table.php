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
        Schema::create('shift_days', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('shift_id');
            $table->foreign('shift_id')->references('id')->on('shifts')->onDelete('cascade');

            $table->unsignedTinyInteger('weekday');
            $table->boolean('is_working_day')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('break_start_time')->nullable();
            $table->time('break_end_time')->nullable();
            $table->integer('break_minutes')->nullable();

            $table->timestamps();

            $table->unique(['shift_id', 'weekday']);
            $table->index('shift_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_days');
    }
};
