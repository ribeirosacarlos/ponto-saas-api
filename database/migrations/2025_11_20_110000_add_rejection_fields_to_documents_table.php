<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->text('rejected_comment')->nullable()->after('notes');
            $table->uuid('rejected_by')->nullable()->after('rejected_comment');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');

            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['rejected_comment', 'rejected_by', 'rejected_at']);
        });
    }
};
