<?php

use App\Support\Commercial\CommercialSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $table = CommercialSchema::table('outreach_settings');

        Schema::create($table, function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('singleton_key')->unique();

            $table->boolean('is_globally_paused')->default(false);
            $table->timestampTz('paused_at')->nullable();
            $table->uuid('paused_by_user_id')->nullable();
            $table->string('pause_reason')->nullable();

            $table->unsignedInteger('daily_send_limit')->default(80);
            $table->unsignedInteger('monthly_send_limit')->default(2400);

            $table->time('sending_window_start_time')->default('08:00:00');
            $table->time('sending_window_end_time')->default('18:00:00');
            $table->json('sending_days')->nullable();
            $table->string('timezone')->default('America/Sao_Paulo');

            $table->unsignedInteger('min_gap_seconds_between_sends')->default(45);
            $table->unsignedInteger('max_sends_per_dispatch_run')->default(6);
            $table->unsignedInteger('bounce_soft_threshold')->default(2);

            $table->uuid('updated_by_user_id')->nullable();

            $table->timestamps();

            $table->foreign('paused_by_user_id')
                ->references('id')->on(CommercialSchema::usersTable())
                ->nullOnDelete();
            $table->foreign('updated_by_user_id')
                ->references('id')->on(CommercialSchema::usersTable())
                ->nullOnDelete();
        });

        DB::table($table)->insert([
            'id' => (string) Str::uuid(),
            'singleton_key' => 'default',
            'sending_days' => json_encode(['mon', 'tue', 'wed', 'thu', 'fri']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists(CommercialSchema::table('outreach_settings'));
    }
};
