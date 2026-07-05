<?php

namespace Tests\Feature\Console;

use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReconcileTimeEntryAdjustmentSequenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_dry_run_reports_without_persisting_changes(): void
    {
        [, $entries] = $this->seedBrokenSequence();

        $this->artisan('time-entries:reconcile-adjustment-sequence')
            ->assertExitCode(0);

        $this->assertSame('in', $entries[0]->fresh()->type);
        $this->assertSame('in', $entries[1]->fresh()->type);
    }

    public function test_apply_fixes_out_of_order_adjustment_types(): void
    {
        [, $entries] = $this->seedBrokenSequence();

        $this->artisan('time-entries:reconcile-adjustment-sequence', ['--apply' => true])
            ->assertExitCode(0);

        $this->assertSame('in', $entries[0]->fresh()->type);
        $this->assertSame('out', $entries[1]->fresh()->type);
        $this->assertSame('work_start', $entries[0]->fresh()->event_kind);
        $this->assertSame('work_end', $entries[1]->fresh()->event_kind);
    }

    public function test_company_filter_limits_reconciliation_scope(): void
    {
        [, $entries] = $this->seedBrokenSequence();

        $this->artisan('time-entries:reconcile-adjustment-sequence', [
            '--company' => (string) Str::uuid(),
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertSame('in', $entries[0]->fresh()->type);
        $this->assertSame('in', $entries[1]->fresh()->type);
    }

    public function test_date_range_filter_excludes_entries_outside_bounds(): void
    {
        [, $entries] = $this->seedBrokenSequence();

        $this->artisan('time-entries:reconcile-adjustment-sequence', [
            '--from' => '2026-03-01',
            '--to' => '2026-03-31',
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertSame('in', $entries[0]->fresh()->type);
        $this->assertSame('in', $entries[1]->fresh()->type);
    }

    /**
     * @return array{0: User, 1: array<int, TimeEntry>}
     */
    private function seedBrokenSequence(): array
    {
        $user = $this->createEmployee();
        $assignment = $this->createShiftDayWithEvents($user, 1, [
            ['kind' => 'work_start', 'time' => '08:00:00', 'day_offset' => 0, 'expected_type' => 'in'],
            ['kind' => 'work_end', 'time' => '17:00:00', 'day_offset' => 0, 'expected_type' => 'out'],
        ]);

        // Reproduz o bug real ja persistido: as duas batidas ficam antes da
        // janela padrao do turno (04:00-21:00) e ambas foram salvas como "in"
        // porque cada ajuste recalculava sua propria janela sem enxergar o
        // registro anterior.
        $first = TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'user_shift_id' => $assignment->id,
            'clocked_at' => CarbonImmutable::parse('2026-02-16 02:00:00', 'Europe/Madrid'),
            'type' => 'in',
            'event_kind' => 'work_start',
            'source' => 'adjustment',
            'adjustment_status' => 'approved',
            'adjustment_reason' => 'Fora do turno/jornada.',
            'adjustment_requested_by' => $user->id,
            'adjustment_requested_at' => now(),
        ]);

        $second = TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'user_shift_id' => $assignment->id,
            'clocked_at' => CarbonImmutable::parse('2026-02-16 03:00:00', 'Europe/Madrid'),
            'type' => 'in',
            'event_kind' => 'work_start',
            'source' => 'adjustment',
            'adjustment_status' => 'approved',
            'adjustment_reason' => 'Fora do turno/jornada.',
            'adjustment_requested_by' => $user->id,
            'adjustment_requested_at' => now(),
        ]);

        return [$user, [$first, $second]];
    }

    private function createEmployee(): User
    {
        $user = User::factory()->create();
        $user->assignRole('employee');

        return $user;
    }

    /**
     * @param  array<int, array{kind: string, time: string, day_offset: int, expected_type: string}>  $events
     */
    private function createShiftDayWithEvents(User $user, int $weekday, array $events): UserShift
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => 'Shift '.Str::random(5),
            'start_time' => $events[0]['time'] ?? '08:00:00',
            'end_time' => $events[array_key_last($events)]['time'] ?? '17:00:00',
            'is_flexible' => false,
            'is_default' => false,
        ]);

        $shiftDay = ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => $weekday,
            'is_working_day' => true,
            'start_time' => $events[0]['time'] ?? '08:00:00',
            'end_time' => $events[array_key_last($events)]['time'] ?? '17:00:00',
        ]);

        foreach ($events as $index => $event) {
            $shiftDay->events()->create([
                'kind' => $event['kind'],
                'expected_time' => $event['time'],
                'day_offset' => $event['day_offset'],
                'expected_type' => $event['expected_type'],
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        return UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);
    }
}
