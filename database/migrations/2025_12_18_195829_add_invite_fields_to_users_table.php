<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable()->after('remember_token');
            $table->timestamp('password_set_at')->nullable()->after('invited_at');
            $table->string('invite_token_hash', 64)->nullable()->after('password_set_at');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_token_hash');
            $table->boolean('must_change_password')->default(false)->after('invite_expires_at');

            $table->index(['company_id', 'invited_at']);
            $table->index('invite_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'invited_at']);
            $table->dropIndex(['invite_token_hash']);
            $table->dropColumn([
                'invited_at',
                'password_set_at',
                'invite_token_hash',
                'invite_expires_at',
                'must_change_password',
            ]);
        });
    }
};
