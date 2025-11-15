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
        Schema::create('adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('company_id');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->uuid('approver_id')->nullable();
            $table->foreign('approver_id')->references('id')->on('users')->onDelete('set null');

            $table->timestamp('original_time')->nullable();
            $table->timestamp('corrected_time')->nullable();
            $table->text('reason')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('adjustments');
    }
};
