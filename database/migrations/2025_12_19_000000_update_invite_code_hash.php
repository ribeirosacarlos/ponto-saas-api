<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['invite_token_hash']);
            $table->dropColumn('invite_token_hash');

            $table->string('invite_code_hash', 64)->nullable()->after('password_set_at');
            $table->index('invite_code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['invite_code_hash']);
            $table->dropColumn('invite_code_hash');

            $table->string('invite_token_hash', 64)->nullable()->after('password_set_at');
            $table->index('invite_token_hash');
        });
    }
};
