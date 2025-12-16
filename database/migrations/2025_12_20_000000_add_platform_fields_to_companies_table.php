<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->after('subscription_ends_at');
            $table->timestamp('blocked_at')->nullable()->after('is_blocked');
            $table->string('blocked_reason')->nullable()->after('blocked_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['is_blocked', 'blocked_at', 'blocked_reason']);
            $table->dropSoftDeletes();
        });
    }
};
