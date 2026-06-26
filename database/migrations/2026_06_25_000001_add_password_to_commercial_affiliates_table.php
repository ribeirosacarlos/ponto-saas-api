<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(CommercialSchema::table('affiliates'), function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table(CommercialSchema::table('affiliates'), function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn('password');
        });
    }
};
