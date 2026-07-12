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
            $table->string('phone_normalized')->nullable()->after('phone');
            $table->string('google_maps_place_id')->nullable()->after('website');

            $table->unique('email');
            $table->unique('phone_normalized');
            $table->unique('google_maps_place_id');
        });
    }

    public function down(): void
    {
        Schema::table(CommercialSchema::table('leads'), function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn(['phone_normalized', 'google_maps_place_id']);
        });
    }
};
