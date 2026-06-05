<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_document', function (Blueprint $table) {
            $table->uuid('absence_id');
            $table->uuid('document_id');
            $table->timestamps();

            $table->primary(['absence_id', 'document_id']);
            $table->foreign('absence_id')->references('id')->on('absences')->cascadeOnDelete();
            $table->foreign('document_id')->references('id')->on('documents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_document');
    }
};
