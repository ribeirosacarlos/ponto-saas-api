<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(CommercialSchema::table('email_sequence_steps'), function (Blueprint $table) {
            $table->uuid('moves_lead_to_step_id')->nullable()->after('is_active');

            $table->foreign('moves_lead_to_step_id')
                ->references('id')->on(CommercialSchema::table('lead_steps'))
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(CommercialSchema::table('email_sequence_steps'), function (Blueprint $table) {
            $table->dropForeign(['moves_lead_to_step_id']);
            $table->dropColumn('moves_lead_to_step_id');
        });
    }
};
