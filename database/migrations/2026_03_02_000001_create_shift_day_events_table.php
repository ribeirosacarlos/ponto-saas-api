<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_day_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('shift_day_id');
            $table->string('kind', 32);
            $table->time('expected_time');
            $table->smallInteger('day_offset')->default(0);
            $table->string('expected_type', 3);
            $table->integer('sort_order');
            $table->timestamps();

            $table->foreign('shift_day_id')->references('id')->on('shift_days')->cascadeOnDelete();
            $table->index(['shift_day_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_day_events');
    }
};
