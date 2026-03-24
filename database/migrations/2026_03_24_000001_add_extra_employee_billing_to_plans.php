<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('extra_employee_price_cents')->nullable()->after('quotas');
            $table->string('stripe_extra_employee_price_id')->nullable()->after('stripe_price_id');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'extra_employee_price_cents',
                'stripe_extra_employee_price_id',
            ]);
        });
    }
};
