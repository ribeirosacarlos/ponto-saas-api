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
            $table->boolean('pauses_outreach_sequences')->default(false)->after('is_final');
        });
    }

    public function down(): void
    {
        Schema::table(CommercialSchema::table('lead_steps'), function (Blueprint $table) {
            $table->dropColumn('pauses_outreach_sequences');
        });
    }
};
