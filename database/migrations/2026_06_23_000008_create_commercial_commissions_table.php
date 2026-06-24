<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        CommercialSchema::ensureSchemaExists();

        Schema::create(CommercialSchema::table('commissions'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('affiliate_id');
            $table->uuid('lead_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->string('invoice_id')->nullable();
            $table->uuid('commission_plan_id');

            $table->decimal('base_amount', 10, 2);
            $table->decimal('commission_percentage', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->unsignedInteger('month_number');

            $table->string('status')->default('pending');
            $table->date('due_date')->nullable();
            $table->timestampTz('paid_at')->nullable();

            $table->timestamps();

            $table->foreign('affiliate_id')
                ->references('id')->on(CommercialSchema::table('affiliates'))
                ->cascadeOnDelete();
            $table->foreign('lead_id')
                ->references('id')->on(CommercialSchema::table('leads'))
                ->nullOnDelete();
            $table->foreign('customer_id')
                ->references('id')->on(CommercialSchema::companiesTable())
                ->nullOnDelete();
            $table->foreign('commission_plan_id')
                ->references('id')->on(CommercialSchema::table('commission_plans'));

            $table->index(['affiliate_id', 'status']);
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('commissions'));
    }
};
