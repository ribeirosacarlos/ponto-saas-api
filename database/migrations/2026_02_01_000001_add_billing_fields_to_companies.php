<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('blocked_reason');
            $table->string('subscription_status')->nullable()->after('stripe_customer_id');
            $table->uuid('current_plan_id')->nullable()->after('subscription_status');

            $table->foreign('current_plan_id')
                ->references('id')
                ->on('plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['current_plan_id']);
            $table->dropColumn(['stripe_customer_id', 'subscription_status', 'current_plan_id']);
        });
    }
};
