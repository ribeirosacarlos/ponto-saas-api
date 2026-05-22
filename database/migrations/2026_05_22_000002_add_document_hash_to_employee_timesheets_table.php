<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_timesheets', function (Blueprint $table) {
            $table->string('document_hash', 64)->nullable()->after('pdf_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('employee_timesheets', function (Blueprint $table) {
            $table->dropColumn('document_hash');
        });
    }
};
