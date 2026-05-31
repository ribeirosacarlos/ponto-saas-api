<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('code', 64)->nullable()->after('slug');
            $table->string('plan_code', 64)->nullable()->after('code');
            $table->unsignedInteger('monthly_price_cents')->nullable()->after('price_cents');
            $table->unsignedInteger('yearly_price_cents')->nullable()->after('monthly_price_cents');
            $table->string('stripe_product_id', 128)->nullable()->after('stripe_price_id');
            $table->string('stripe_extra_employee_product_id', 128)->nullable()->after('stripe_product_id');
            $table->unsignedInteger('included_employees')->nullable()->after('extra_employee_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'code',
                'plan_code',
                'monthly_price_cents',
                'yearly_price_cents',
                'stripe_product_id',
                'stripe_extra_employee_product_id',
                'included_employees',
            ]);
        });
    }
};
