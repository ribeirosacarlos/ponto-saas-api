<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(CommercialSchema::table('email_sends'), function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('enrollment_id');
            $table->uuid('lead_id');
            $table->uuid('sequence_step_id');
            $table->uuid('template_id');

            $table->string('idempotency_key')->unique();

            $table->string('to_email');
            $table->text('rendered_subject');
            $table->text('rendered_body_html');

            $table->string('resend_message_id')->nullable()->unique();

            $table->string('status')->default('queued');

            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('first_clicked_at')->nullable();
            $table->timestampTz('bounced_at')->nullable();
            $table->timestampTz('complained_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();

            $table->text('failure_reason')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);

            $table->timestamps();

            $table->foreign('enrollment_id')
                ->references('id')->on(CommercialSchema::table('email_sequence_enrollments'))
                ->restrictOnDelete();
            $table->foreign('lead_id')
                ->references('id')->on(CommercialSchema::table('leads'))
                ->restrictOnDelete();
            $table->foreign('sequence_step_id')
                ->references('id')->on(CommercialSchema::table('email_sequence_steps'))
                ->restrictOnDelete();
            $table->foreign('template_id')
                ->references('id')->on(CommercialSchema::table('email_templates'))
                ->restrictOnDelete();

            $table->index('status');
            $table->index('lead_id');
            $table->index('enrollment_id');
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('email_sends'));
    }
};
