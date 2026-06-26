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
            $table->string('invite_code_hash')->nullable()->after('password');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table(CommercialSchema::table('affiliates'), function (Blueprint $table) {
            $table->dropColumn(['invite_code_hash', 'invite_expires_at']);
        });
    }
};
