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

        Schema::create(CommercialSchema::table('leads'), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('website')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->string('segment')->nullable();
            $table->unsignedInteger('employees_count')->nullable();
            $table->string('source')->nullable();

            $table->uuid('affiliate_id')->nullable();
            $table->uuid('current_step_id')->nullable();
            $table->uuid('assigned_to_user_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();

            $table->string('status')->default('new');
            $table->string('priority')->default('medium');
            $table->unsignedInteger('score')->default(0);
            $table->text('general_notes')->nullable();

            $table->string('next_action_type')->nullable();
            $table->timestampTz('next_action_at')->nullable();
            $table->uuid('next_action_user_id')->nullable();

            $table->timestampTz('converted_at')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->string('lost_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('affiliate_id')
                ->references('id')->on(CommercialSchema::table('affiliates'))
                ->nullOnDelete();
            $table->foreign('current_step_id')
                ->references('id')->on(CommercialSchema::table('lead_steps'))
                ->nullOnDelete();
            $table->foreign('assigned_to_user_id')
                ->references('id')->on(CommercialSchema::usersTable())
                ->nullOnDelete();
            $table->foreign('created_by_user_id')
                ->references('id')->on(CommercialSchema::usersTable())
                ->nullOnDelete();
            $table->foreign('next_action_user_id')
                ->references('id')->on(CommercialSchema::usersTable())
                ->nullOnDelete();
            $table->foreign('customer_id')
                ->references('id')->on(CommercialSchema::companiesTable())
                ->nullOnDelete();

            $table->index('status');
            $table->index('priority');
            $table->index('assigned_to_user_id');
            $table->index('affiliate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('leads'));
    }
};
