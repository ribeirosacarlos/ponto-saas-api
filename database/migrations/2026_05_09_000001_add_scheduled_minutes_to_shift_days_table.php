<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shift_days', function (Blueprint $table) {
            $table->integer('scheduled_minutes')->nullable()->after('end_time');
        });

        DB::table('shift_days')
            ->select(['id', 'start_time', 'end_time'])
            ->orderBy('id')
            ->chunkById(100, function ($days): void {
                foreach ($days as $day) {
                    if (! $day->start_time || ! $day->end_time) {
                        continue;
                    }

                    $start = $this->parseTimeToMinutes($day->start_time);
                    $end = $this->parseTimeToMinutes($day->end_time);

                    if ($start === null || $end === null || $end < $start) {
                        continue;
                    }

                    DB::table('shift_days')
                        ->where('id', $day->id)
                        ->update([
                            'scheduled_minutes' => $end - $start,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('shift_days', function (Blueprint $table) {
            $table->dropColumn('scheduled_minutes');
        });
    }

    private function parseTimeToMinutes(?string $value): ?int
    {
        if (! $value) {
            return null;
        }

        foreach (['H:i:s', 'H:i'] as $format) {
            $parsed = CarbonImmutable::createFromFormat($format, $value);

            if ($parsed !== false) {
                return ((int) $parsed->format('H') * 60) + (int) $parsed->format('i');
            }
        }

        return null;
    }
};
