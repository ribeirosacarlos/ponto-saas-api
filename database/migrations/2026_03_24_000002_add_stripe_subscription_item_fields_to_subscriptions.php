<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_subscription_item_id')->nullable()->after('stripe_price_id');
            $table->string('stripe_extra_subscription_item_id')->nullable()->after('stripe_subscription_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_subscription_item_id',
                'stripe_extra_subscription_item_id',
            ]);
        });
    }
};
