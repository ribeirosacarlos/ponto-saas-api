<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_status', 64)->nullable()->after('stripe_subscription_id');
            $table->unsignedInteger('included_employees')->nullable()->after('stripe_status');
            $table->unsignedInteger('active_employees')->default(0)->after('included_employees');
            $table->timestamp('grace_period_ends_at')->nullable()->after('past_due_since');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_status',
                'included_employees',
                'active_employees',
                'grace_period_ends_at',
            ]);
        });
    }
};
