<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(CommercialSchema::table('leads'), function (Blueprint $table) {
            $table->timestampTz('current_step_started_at')->nullable()->after('current_step_id');
        });
    }

    public function down(): void
    {
        Schema::table(CommercialSchema::table('leads'), function (Blueprint $table) {
            $table->dropColumn('current_step_started_at');
        });
    }
};
