<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(CommercialSchema::table('lead_steps'), function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->boolean('is_final')->default(false)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table(CommercialSchema::table('lead_steps'), function (Blueprint $table) {
            $table->dropColumn(['slug', 'is_final']);
        });
    }
};
