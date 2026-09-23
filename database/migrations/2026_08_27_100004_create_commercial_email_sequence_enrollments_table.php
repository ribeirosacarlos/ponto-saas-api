<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = CommercialSchema::table('email_sequence_enrollments');

        Schema::create($table, function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('lead_id');
            $table->uuid('sequence_id');

            $table->string('status')->default('active');
            $table->string('exit_reason')->nullable();

            $table->uuid('current_step_id')->nullable();
            $table->uuid('next_step_id')->nullable();
            $table->timestampTz('next_send_at')->nullable();

            $table->timestampTz('enrolled_at');
            $table->uuid('enrolled_by_user_id')->nullable();

            $table->timestampTz('paused_at')->nullable();
            $table->uuid('paused_by_user_id')->nullable();
            $table->string('pause_reason')->nullable();

            $table->timestampTz('replied_at')->nullable();
            $table->uuid('replied_marked_by_user_id')->nullable();

            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();

            $table->string('unsubscribe_token', 64)->unique();

            $table->timestamps();

            $table->foreign('lead_id')
                ->references('id')->on(CommercialSchema::table('leads'))
                ->restrictOnDelete();
            $table->foreign('sequence_id')
                ->references('id')->on(CommercialSchema::table('email_sequences'))
                ->restrictOnDelete();
            $table->foreign('current_step_id')
                ->references('id')->on(CommercialSchema::table('email_sequence_steps'))
                ->nullOnDelete();
            $table->foreign('next_step_id')
                ->references('id')->on(CommercialSchema::table('email_sequence_steps'))
                ->nullOnDelete();
            $table->foreign('enrolled_by_user_id')
                ->references('id')->on(CommercialSchema::usersTable())
                ->nullOnDelete();
            $table->foreign('paused_by_user_id')
                ->references('id')->on(CommercialSchema::usersTable())
                ->nullOnDelete();
            $table->foreign('replied_marked_by_user_id')
                ->references('id')->on(CommercialSchema::usersTable())
                ->nullOnDelete();

            $table->index('status');
            $table->index('next_send_at');
            $table->index('lead_id');
        });

        // Impede mais de uma inscrição ativa/pausada do mesmo lead na mesma sequência.
        // SQLite (usado nos testes) não suporta índice único parcial — a checagem
        // equivalente é feita na camada de aplicação (CommercialEmailEnrollmentService).
        if (CommercialSchema::isPgsql()) {
            DB::statement(
                "CREATE UNIQUE INDEX commercial_email_sequence_enrollments_lead_sequence_active_unique
                 ON {$table} (lead_id, sequence_id)
                 WHERE status IN ('active', 'paused')"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('email_sequence_enrollments'));
    }
};
