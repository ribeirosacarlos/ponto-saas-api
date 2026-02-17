<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('original_name', 255)->nullable()->after('storage_disk');
            $table->uuid('uploaded_by')->nullable()->after('original_name');

            $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'user_id', 'uploaded_by'], 'documents_company_user_uploaded_by_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_company_user_uploaded_by_idx');
            $table->dropForeign(['uploaded_by']);
            $table->dropColumn(['original_name', 'uploaded_by']);
        });
    }
};
