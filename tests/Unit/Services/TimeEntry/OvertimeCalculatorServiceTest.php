<?php

namespace Tests\Unit\Services\TimeEntry;

use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use App\Services\TimeEntry\OvertimeCalculatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function assignShift(User $user, int $weekday, array $options = []): Shift
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => $options['name'] ?? 'Shift '.$weekday,
            'start_time' => $options['start_time'] ?? '08:00',
            'end_time' => $options['end_time'] ?? '17:00',
            'is_flexible' => false,
            'is_default' => false,
        ]);

        ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => $weekday,
            'is_working_day' => true,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'scheduled_minutes' => $options['scheduled_minutes'] ?? 540,
            'break_minutes' => $options['break_minutes'] ?? 60,
            'break_start_time' => $options['break_start_time'] ?? '12:00',
            'break_end_time' => $options['break_end_time'] ?? '13:00',
        ]);

        UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => ($options['start_date'] ?? CarbonImmutable::parse('2025-12-01'))->toDateString(),
            'end_date' => null,
        ]);

        return $shift;
    }

    private function assignWeeklyShift(User $user, array $options = []): Shift
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => $options['name'] ?? 'Weekly shift',
            'start_time' => $options['start_time'] ?? '08:00',
            'end_time' => $options['end_time'] ?? '14:00',
            'is_flexible' => false,
            'is_default' => false,
        ]);

        foreach (range(1, 5) as $weekday) {
            ShiftDay::create([
                'shift_id' => $shift->id,
                'weekday' => $weekday,
                'is_working_day' => true,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'scheduled_minutes' => $options['scheduled_minutes'] ?? 340,
                'break_minutes' => $options['break_minutes'] ?? 0,
                'break_start_time' => $options['break_start_time'] ?? null,
                'break_end_time' => $options['break_end_time'] ?? null,
            ]);
        }

        UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => ($options['start_date'] ?? CarbonImmutable::parse('2025-12-01'))->toDateString(),
            'end_date' => null,
        ]);

        return $shift;
    }

    private function createTimeEntry(User $user, string $type, CarbonImmutable $clockedAt): TimeEntry
    {
        return TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => $clockedAt,
            'type' => $type,
            'source' => 'web',
        ]);
    }

    private function createSinglePairWorkday(User $user, CarbonImmutable $date, int $workedMinutes): void
    {
        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(8, 0)->addMinutes($workedMinutes));
    }

    public static function adjustmentPairs(): array
    {
        return [
            'pending return' => [null, 'pending', null, 240, 0, false, 1],
            'pending departure' => [null, null, 'pending', 240, 50, true, 1],
            'pending afternoon' => [null, 'pending', 'pending', 240, 0, false, 1],
            'pending lunch boundaries' => ['pending', 'pending', null, 0, 0, false, 0],
            'approved return' => [null, 'approved', null, 610, 50, false, 2],
            'rejected return' => [null, 'rejected', null, 240, 0, false, 1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('adjustmentPairs')]
    public function test_only_valid_pairs_count_while_pending_entries_keep_their_positions(
        ?string $lunchStatus,
        ?string $returnStatus,
        ?string $departureStatus,
        int $workedMinutes,
        int $breakMinutes,
        bool $openSession,
        int $pairCount
    ): void {
        CarbonImmutable::setTestNow('2026-09-23 12:00:00');
        $date = CarbonImmutable::parse('2026-09-22', 'UTC');
        $user = User::factory()->create();
        $user->company->update(['timezone' => 'UTC']);
        $this->assignShift($user, $date->isoWeekday());

        foreach ([['08:00', 'in', null], ['12:00', 'out', $lunchStatus], ['12:50', 'in', $returnStatus], ['19:00', 'out', $departureStatus]] as [$time, $type, $status]) {
            TimeEntry::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'clocked_at' => $date->setTimeFromTimeString($time),
                'type' => $type,
                'source' => $status ? 'adjustment' : 'web',
                'adjustment_status' => $status,
            ]);
        }

        $result = app(OvertimeCalculatorService::class)->calculateForEmployee($user, $date, $date, true);
        $summary = $result['days'][0]['summary'];
        $this->assertSame($workedMinutes, $summary['worked_minutes']);
        $this->assertSame($workedMinutes, $summary['raw_worked_minutes']);
        $this->assertSame($workedMinutes, $result['totals']['worked_minutes']);
        $this->assertSame(max(0, $workedMinutes - 540), $result['totals']['extra_minutes']);
        $this->assertSame($breakMinutes, $summary['real_break_minutes']);
        $this->assertSame($openSession, $summary['open_session']);
        $this->assertSame($pairCount, $summary['pair_count']);
        $this->assertCount($pairCount, $summary['pair_details']);
        $this->assertSame($workedMinutes !== 610, $summary['has_incomplete_entries']);
        if ($openSession) {
            $this->assertSame($date->setTime(12, 50)->toIso8601String(), $summary['open_pair']['in']);
        } else {
            $this->assertNull($summary['open_pair']);
        }
    }

    public function test_pending_out_does_not_close_a_valid_in_or_bridge_to_a_later_out(): void
    {
        $date = CarbonImmutable::parse('2025-12-15', 'UTC');
        $user = User::factory()->create();
        $user->company->update(['timezone' => 'UTC']);
        $this->assignShift($user, $date->isoWeekday());
        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $pending = $this->createTimeEntry($user, 'out', $date->setTime(12, 0));
        $pending->forceFill(['adjustment_status' => 'pending'])->saveQuietly();
        $this->createTimeEntry($user, 'out', $date->setTime(19, 0));

        $result = app(OvertimeCalculatorService::class)->calculateForEmployee($user, $date, $date, true);
        $this->assertSame(0, $result['totals']['worked_minutes']);
        $this->assertSame(0, $result['totals']['extra_minutes']);
        $this->assertTrue($result['days'][0]['summary']['open_session']);
        $this->assertSame($date->setTime(8, 0)->toIso8601String(), $result['days'][0]['summary']['open_pair']['in']);
    }

    public function test_pending_entries_do_not_start_counting_since_or_remove_valid_overtime(): void
    {
        $date = CarbonImmutable::parse('2025-12-15', 'UTC');
        $user = User::factory()->create();
        $user->company->update(['timezone' => 'UTC']);
        $this->assignShift($user, $date->isoWeekday());
        $pending = $this->createTimeEntry($user, 'in', $date->subDay()->setTime(8, 0));
        $pending->forceFill(['adjustment_status' => 'pending'])->saveQuietly();
        $this->createSinglePairWorkday($user, $date, 600);
        $pending = $this->createTimeEntry($user, 'in', $date->setTime(19, 0));
        $pending->forceFill(['adjustment_status' => 'pending'])->saveQuietly();
        $this->createTimeEntry($user, 'out', $date->setTime(20, 0));

        $result = app(OvertimeCalculatorService::class)->calculateForEmployee($user, null, $date, true);
        $this->assertSame('2025-12-15', $result['counting_since']);
        $this->assertSame(600, $result['totals']['worked_minutes']);
        $this->assertSame(60, $result['totals']['extra_minutes']);
    }

    public function test_day_with_multiple_pairs_accumulates_worked_minutes(): void
    {
        $date = CarbonImmutable::parse('2025-12-15', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday());

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(12, 0));
        $this->createTimeEntry($user, 'in', $date->setTime(13, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(18, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);
        $this->assertEquals(540, $result['totals']['worked_minutes']);
        $this->assertEquals(0, $result['totals']['extra_minutes']);
        $this->assertEquals('00:00', $result['totals']['balance_hhmm']);
        $this->assertEquals('even', $result['days'][0]['status']);
        $this->assertFalse($result['days'][0]['ignored']);
        $this->assertEquals(0, $result['days'][0]['balance_minutes']);
    }

    public function test_open_pair_keeps_closed_pairs_counted_for_the_day(): void
    {
        $date = CarbonImmutable::parse('2025-12-16', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday());

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(12, 0));
        $this->createTimeEntry($user, 'in', $date->setTime(13, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertEquals(240, $result['totals']['worked_minutes']);
        $this->assertEquals(-300, $result['totals']['debt_minutes']);
        $this->assertEquals(240, $result['days'][0]['worked_minutes']);
        $this->assertEquals(240, $result['days'][0]['raw_worked_minutes']);
        $this->assertTrue($result['days'][0]['has_incomplete_entries']);
        $this->assertTrue($result['days'][0]['open_session']);
        $this->assertFalse($result['days'][0]['ignored']);
        $this->assertNull($result['days'][0]['reason']);
    }

    public function test_open_pair_keeps_closed_pair_extra_counted_for_overtime(): void
    {
        $date = CarbonImmutable::parse('2025-12-17', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday(), [
            'scheduled_minutes' => 240,
            'break_minutes' => 0,
        ]);

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(13, 0));
        $this->createTimeEntry($user, 'in', $date->setTime(14, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertEquals(300, $result['totals']['worked_minutes']);
        $this->assertEquals(60, $result['totals']['extra_minutes']);
        $this->assertEquals('+01:00', $result['days'][0]['balance_hhmm']);
        $this->assertEquals('extra', $result['days'][0]['status']);
        $this->assertFalse($result['days'][0]['ignored']);
    }

    public function test_day_not_defined_in_shift_is_all_extra(): void
    {
        $date = CarbonImmutable::parse('2025-12-14', 'UTC'); // Sunday
        $user = User::factory()->create();
        $this->assignShift($user, 1); // only Monday defined

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(17, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);
        $this->assertEquals(540, $result['totals']['extra_minutes']);
        $this->assertEquals('extra', $result['days'][0]['status']);
        $this->assertEquals(0, $result['days'][0]['expected_minutes']);
    }

    public function test_worked_less_than_expected_produces_negative_balance(): void
    {
        $date = CarbonImmutable::parse('2025-12-17', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday());

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(12, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertEquals(-300, $result['totals']['debt_minutes']);
        $this->assertEquals(300, $result['totals']['abs_debt_minutes']);
        $this->assertEquals('debt', $result['days'][0]['status']);
        $this->assertEquals('-05:00', $result['days'][0]['balance_hhmm']);
    }

    public function test_from_defaults_to_first_entry_date_when_null(): void
    {
        $firstDay = CarbonImmutable::parse('2025-12-17', 'UTC');
        $secondDay = CarbonImmutable::parse('2025-12-20', 'UTC');

        $user = User::factory()->create();
        $this->assignShift($user, $firstDay->isoWeekday());

        // first entry is on firstDay
        $this->createTimeEntry($user, 'in', $firstDay->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $firstDay->setTime(17, 0));

        $this->createTimeEntry($user, 'in', $secondDay->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $secondDay->setTime(17, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, null, $secondDay, true);

        $this->assertEquals('2025-12-17', $result['from']);
        $this->assertCount(4, $result['days']); // 17, 18, 19, 20
        $this->assertEquals('2025-12-17', $result['days'][0]['date']);
    }

    public function test_company_timezone_affects_grouping(): void
    {
        $date = CarbonImmutable::parse('2025-12-18', 'America/Sao_Paulo');
        $user = User::factory()->create();
        $user->company->update(['timezone' => 'America/Sao_Paulo']);
        $this->assignShift($user, $date->isoWeekday());

        // clocked_at é armazenado como horário local da empresa (sem timezone),
        // então 21:30-23:30 representa o período dentro do dia 2025-12-18
        // em America/Sao_Paulo.
        $this->createTimeEntry($user, 'in', CarbonImmutable::parse('2025-12-18 21:30:00', 'UTC'));
        $this->createTimeEntry($user, 'out', CarbonImmutable::parse('2025-12-18 23:30:00', 'UTC'));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);
        $this->assertEquals('2025-12-18', $result['days'][0]['date']);
        $this->assertEquals(120, $result['days'][0]['worked_minutes']);
    }

    public function test_expected_minutes_use_daily_scheduled_minutes_without_adding_break(): void
    {
        $date = CarbonImmutable::parse('2025-12-19', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday(), [
            'start_time' => '08:00',
            'end_time' => '14:00',
            'scheduled_minutes' => 340,
            'break_minutes' => 20,
            'break_start_time' => '12:00',
            'break_end_time' => '12:20',
        ]);

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(12, 0));
        $this->createTimeEntry($user, 'in', $date->setTime(12, 20));
        $this->createTimeEntry($user, 'out', $date->setTime(14, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertSame(340, $result['days'][0]['expected_minutes']);
        $this->assertSame(340, $result['days'][0]['worked_minutes']);
        $this->assertSame(340, $result['days'][0]['actual_worked_minutes']);
        $this->assertSame(20, $result['days'][0]['counted_break_minutes']);
        $this->assertSame('even', $result['days'][0]['status']);
    }

    public function test_break_time_below_allowed_counts_only_real_break(): void
    {
        $date = CarbonImmutable::parse('2025-12-20', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday(), [
            'start_time' => '08:00',
            'end_time' => '14:00',
            'scheduled_minutes' => 340,
            'break_minutes' => 20,
            'break_start_time' => '12:00',
            'break_end_time' => '12:20',
        ]);

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(12, 0));
        $this->createTimeEntry($user, 'in', $date->setTime(12, 15));
        $this->createTimeEntry($user, 'out', $date->setTime(14, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertSame(345, $result['days'][0]['raw_worked_minutes']);
        $this->assertSame(15, $result['days'][0]['real_break_minutes']);
        $this->assertSame(15, $result['days'][0]['counted_break_minutes']);
        $this->assertSame(0, $result['days'][0]['exceeded_break_minutes']);
        $this->assertSame(345, $result['days'][0]['worked_minutes']);
        $this->assertSame(5, $result['days'][0]['balance_minutes']);
    }

    public function test_break_time_above_allowed_preserves_worked_time_and_reduces_balance(): void
    {
        $date = CarbonImmutable::parse('2025-12-22', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday(), [
            'start_time' => '08:00',
            'end_time' => '14:00',
            'scheduled_minutes' => 340,
            'break_minutes' => 20,
            'break_start_time' => '12:00',
            'break_end_time' => '12:20',
        ]);

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(12, 0));
        $this->createTimeEntry($user, 'in', $date->setTime(12, 25));
        $this->createTimeEntry($user, 'out', $date->setTime(14, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertSame(335, $result['days'][0]['raw_worked_minutes']);
        $this->assertSame(20, $result['days'][0]['counted_break_minutes']);
        $this->assertSame(25, $result['days'][0]['actual_break_minutes']);
        $this->assertSame(5, $result['days'][0]['exceeded_break_minutes']);
        $this->assertSame(335, $result['days'][0]['worked_minutes']);
        $this->assertSame(-5, $result['days'][0]['balance_minutes']);
        $this->assertSame('debt', $result['days'][0]['status']);
    }

    public function test_no_break_does_not_add_allowed_break_minutes(): void
    {
        $date = CarbonImmutable::parse('2025-12-23', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday(), [
            'start_time' => '08:00',
            'end_time' => '14:00',
            'scheduled_minutes' => 340,
            'break_minutes' => 20,
            'break_start_time' => '12:00',
            'break_end_time' => '12:20',
        ]);

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(14, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertSame(360, $result['days'][0]['raw_worked_minutes']);
        $this->assertSame(0, $result['days'][0]['real_break_minutes']);
        $this->assertSame(0, $result['days'][0]['counted_break_minutes']);
        $this->assertSame(360, $result['days'][0]['worked_minutes']);
        $this->assertSame(20, $result['days'][0]['balance_minutes']);
    }

    public function test_multiple_breaks_share_daily_allowed_break_limit(): void
    {
        $date = CarbonImmutable::parse('2025-12-24', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday(), [
            'start_time' => '08:00',
            'end_time' => '14:00',
            'scheduled_minutes' => 340,
            'break_minutes' => 20,
            'break_start_time' => '12:00',
            'break_end_time' => '12:20',
        ]);

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(10, 0));
        $this->createTimeEntry($user, 'in', $date->setTime(10, 15));
        $this->createTimeEntry($user, 'out', $date->setTime(12, 0));
        $this->createTimeEntry($user, 'in', $date->setTime(12, 15));
        $this->createTimeEntry($user, 'out', $date->setTime(14, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertSame(330, $result['days'][0]['raw_worked_minutes']);
        $this->assertSame(30, $result['days'][0]['real_break_minutes']);
        $this->assertSame(20, $result['days'][0]['counted_break_minutes']);
        $this->assertSame(10, $result['days'][0]['exceeded_break_minutes']);
        $this->assertSame(330, $result['days'][0]['worked_minutes']);
        $this->assertSame(-10, $result['days'][0]['balance_minutes']);
    }

    public function test_reported_exceeded_break_case_keeps_worked_time_as_pair_sum(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-16 09:00:00', 'America/Sao_Paulo'));

        $date = CarbonImmutable::parse('2026-05-15', 'America/Sao_Paulo');
        $user = User::factory()->create();
        $user->company->update(['timezone' => 'America/Sao_Paulo']);
        $this->assignShift($user, $date->isoWeekday(), [
            'start_time' => '10:31',
            'end_time' => '17:59',
            'scheduled_minutes' => 340,
            'break_minutes' => 20,
            'break_start_time' => '11:32',
            'break_end_time' => '11:52',
            'start_date' => $date->startOfMonth(),
        ]);

        $this->createTimeEntry($user, 'in', $date->setTime(10, 31)->utc());
        $this->createTimeEntry($user, 'out', $date->setTime(11, 32)->utc());
        $this->createTimeEntry($user, 'in', $date->setTime(13, 44)->utc());
        $this->createTimeEntry($user, 'out', $date->setTime(17, 59)->utc());

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertSame(316, $result['days'][0]['raw_worked_minutes']);
        $this->assertSame(316, $result['days'][0]['actual_worked_minutes']);
        $this->assertSame(316, $result['days'][0]['worked_minutes']);
        $this->assertSame('05:16', $result['days'][0]['raw_worked_hhmm']);
        $this->assertSame('05:16', $result['days'][0]['actual_worked_hhmm']);
        $this->assertSame('05:16', $result['days'][0]['worked_hhmm']);
        $this->assertSame(132, $result['days'][0]['real_break_minutes']);
        $this->assertSame('02:12', $result['days'][0]['real_break_hhmm']);
        $this->assertSame(132, $result['days'][0]['actual_break_minutes']);
        $this->assertSame('02:12', $result['days'][0]['actual_break_hhmm']);
        $this->assertSame(20, $result['days'][0]['allowed_break_minutes']);
        $this->assertSame('00:20', $result['days'][0]['allowed_break_hhmm']);
        $this->assertSame(112, $result['days'][0]['exceeded_break_minutes']);
        $this->assertSame('01:52', $result['days'][0]['exceeded_break_hhmm']);
        $this->assertSame(-24, $result['days'][0]['balance_minutes']);
        $this->assertSame('-00:24', $result['days'][0]['balance_hhmm']);
        $this->assertSame(0, $result['days'][0]['extra_minutes']);
        $this->assertSame('00:00', $result['days'][0]['extra_hhmm']);
        $this->assertSame(-24, $result['days'][0]['debt_minutes']);
        $this->assertSame('00:24', $result['days'][0]['debt_hhmm']);
        $this->assertSame('debt', $result['days'][0]['status']);
    }

    public function test_reported_day_summary_case_does_not_count_allowed_break_as_worked_time(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-20 09:00:00', 'America/Sao_Paulo'));

        $date = CarbonImmutable::parse('2026-05-19', 'America/Sao_Paulo');
        $user = User::factory()->create();
        $user->company->update(['timezone' => 'America/Sao_Paulo']);
        $this->assignShift($user, $date->isoWeekday(), [
            'start_time' => '10:52',
            'end_time' => '16:52',
            'scheduled_minutes' => 340,
            'break_minutes' => 20,
            'break_start_time' => '16:05',
            'break_end_time' => '16:25',
            'start_date' => $date->startOfMonth(),
        ]);

        $this->createTimeEntry($user, 'in', $date->setTime(10, 52)->utc());
        $this->createTimeEntry($user, 'out', $date->setTime(16, 5)->utc());
        $this->createTimeEntry($user, 'in', $date->setTime(16, 28)->utc());
        $this->createTimeEntry($user, 'out', $date->setTime(18, 38)->utc());

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertSame(443, $result['days'][0]['raw_worked_minutes']);
        $this->assertSame('07:23', $result['days'][0]['raw_worked_hhmm']);
        $this->assertSame(443, $result['days'][0]['actual_worked_minutes']);
        $this->assertSame(23, $result['days'][0]['real_break_minutes']);
        $this->assertSame(20, $result['days'][0]['counted_break_minutes']);
        $this->assertSame(3, $result['days'][0]['exceeded_break_minutes']);
        $this->assertSame(443, $result['days'][0]['worked_minutes']);
        $this->assertSame('07:23', $result['days'][0]['worked_hhmm']);
        $this->assertSame(100, $result['days'][0]['balance_minutes']);
        $this->assertSame('+01:40', $result['days'][0]['balance_hhmm']);
        $this->assertSame(100, $result['days'][0]['extra_minutes']);
        $this->assertSame('01:40', $result['days'][0]['extra_hhmm']);
        $this->assertSame(0, $result['days'][0]['debt_minutes']);
        $this->assertSame('00:00', $result['days'][0]['debt_hhmm']);
    }

    public function test_seconds_are_ignored_in_work_and_break_calculation(): void
    {
        $date = CarbonImmutable::parse('2025-12-26', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday(), [
            'start_time' => '08:00',
            'end_time' => '14:00',
            'scheduled_minutes' => 360,
            'break_minutes' => 20,
            'break_start_time' => '12:00',
            'break_end_time' => '12:20',
        ]);

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0, 59));
        $this->createTimeEntry($user, 'out', $date->setTime(12, 0, 1));
        $this->createTimeEntry($user, 'in', $date->setTime(12, 20, 59));
        $this->createTimeEntry($user, 'out', $date->setTime(14, 0, 1));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertSame(340, $result['days'][0]['raw_worked_minutes']);
        $this->assertSame(20, $result['days'][0]['real_break_minutes']);
        $this->assertSame(20, $result['days'][0]['counted_break_minutes']);
        $this->assertSame(340, $result['days'][0]['worked_minutes']);
    }

    public function test_current_day_is_not_finalized_for_overtime_balance(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-12-19 15:00:00', 'UTC'));

        $date = CarbonImmutable::parse('2025-12-19', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday(), [
            'start_time' => '08:00',
            'end_time' => '14:00',
            'scheduled_minutes' => 340,
            'break_minutes' => 20,
            'break_start_time' => '12:00',
            'break_end_time' => '12:20',
        ]);

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(12, 0));
        $this->createTimeEntry($user, 'in', $date->setTime(12, 25));
        $this->createTimeEntry($user, 'out', $date->setTime(14, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertSame(0, $result['totals']['worked_minutes']);
        $this->assertSame(0, $result['totals']['expected_minutes']);
        $this->assertSame(0, $result['totals']['balance_minutes']);
        $this->assertSame(0, $result['totals']['extra_minutes']);
        $this->assertSame(0, $result['totals']['debt_minutes']);
        $this->assertSame(335, $result['days'][0]['worked_minutes']);
        $this->assertSame(5, $result['days'][0]['exceeded_break_minutes']);
        $this->assertSame(0, $result['days'][0]['balance_minutes']);
        $this->assertSame(0, $result['days'][0]['extra_minutes']);
        $this->assertSame(0, $result['days'][0]['debt_minutes']);
        $this->assertSame('even', $result['days'][0]['status']);
        $this->assertFalse($result['days'][0]['is_finalized']);
    }

    public function test_current_day_is_excluded_from_overtime_totals_when_period_has_previous_days(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2025-12-19 15:00:00', 'UTC'));

        $yesterday = CarbonImmutable::parse('2025-12-18', 'UTC');
        $today = CarbonImmutable::parse('2025-12-19', 'UTC');
        $user = User::factory()->create();
        $this->assignWeeklyShift($user, [
            'scheduled_minutes' => 340,
            'start_date' => $yesterday,
        ]);

        $this->createSinglePairWorkday($user, $yesterday, 360);
        $this->createSinglePairWorkday($user, $today, 480);

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $yesterday, $today, true);

        $this->assertSame(360, $result['totals']['worked_minutes']);
        $this->assertSame(340, $result['totals']['expected_minutes']);
        $this->assertSame(20, $result['totals']['balance_minutes']);
        $this->assertSame(20, $result['totals']['extra_minutes']);
        $this->assertSame(0, $result['totals']['debt_minutes']);
        $this->assertTrue($result['days'][0]['is_finalized']);
        $this->assertFalse($result['days'][1]['is_finalized']);
        $this->assertSame(480, $result['days'][1]['worked_minutes']);
        $this->assertSame(0, $result['days'][1]['balance_minutes']);
    }

    public function test_period_balance_uses_total_worked_minus_expected_for_positive_reported_case(): void
    {
        $from = CarbonImmutable::parse('2025-12-01', 'UTC');
        $to = CarbonImmutable::parse('2025-12-26', 'UTC');
        $user = User::factory()->create();
        $this->assignWeeklyShift($user, [
            'scheduled_minutes' => 340,
            'start_date' => $from,
        ]);

        $workedMinutesByDay = array_fill(0, 19, 348);
        $workedMinutesByDay[] = 353;
        $index = 0;

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }

            $this->createSinglePairWorkday($user, $date, $workedMinutesByDay[$index]);
            $index++;
        }

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $from, $to, true);

        $this->assertSame(6965, $result['totals']['worked_minutes']);
        $this->assertSame(6800, $result['totals']['expected_minutes']);
        $this->assertSame(165, $result['totals']['balance_minutes']);
        $this->assertSame(165, $result['totals']['extra_minutes']);
        $this->assertSame(0, $result['totals']['debt_minutes']);
        $this->assertSame('+02:45', $result['totals']['balance_hhmm']);
        $this->assertSame(
            $result['totals']['balance_minutes'],
            $result['totals']['extra_minutes'] + $result['totals']['debt_minutes']
        );
    }

    public function test_period_balance_uses_total_worked_minus_expected_for_negative_reported_case(): void
    {
        $from = CarbonImmutable::parse('2025-12-01', 'UTC');
        $to = CarbonImmutable::parse('2025-12-26', 'UTC');
        $user = User::factory()->create();
        $this->assignWeeklyShift($user, [
            'scheduled_minutes' => 340,
            'start_date' => $from,
        ]);

        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            if ($date->isWeekend()) {
                continue;
            }

            $this->createSinglePairWorkday($user, $date, 300);
        }

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $from, $to, true);

        $this->assertSame(6000, $result['totals']['worked_minutes']);
        $this->assertSame(6800, $result['totals']['expected_minutes']);
        $this->assertSame(-800, $result['totals']['balance_minutes']);
        $this->assertSame(0, $result['totals']['extra_minutes']);
        $this->assertSame(-800, $result['totals']['debt_minutes']);
        $this->assertSame('-13:20', $result['totals']['balance_hhmm']);
        $this->assertSame('13:20', $result['totals']['debt_hhmm']);
        $this->assertSame(
            $result['totals']['balance_minutes'],
            $result['totals']['extra_minutes'] + $result['totals']['debt_minutes']
        );
    }

    public function test_period_balance_consolidates_mixed_positive_and_negative_days(): void
    {
        $from = CarbonImmutable::parse('2025-12-01', 'UTC');
        $to = CarbonImmutable::parse('2025-12-03', 'UTC');
        $user = User::factory()->create();
        $this->assignWeeklyShift($user, [
            'scheduled_minutes' => 340,
            'start_date' => $from,
        ]);

        $this->createSinglePairWorkday($user, $from, 360);
        $this->createSinglePairWorkday($user, $from->addDay(), 300);
        $this->createSinglePairWorkday($user, $from->addDays(2), 380);

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $from, $to, true);

        $this->assertSame([20, -40, 40], array_column($result['days'], 'balance_minutes'));
        $this->assertSame(1040, $result['totals']['worked_minutes']);
        $this->assertSame(1020, $result['totals']['expected_minutes']);
        $this->assertSame(20, $result['totals']['balance_minutes']);
        $this->assertSame(20, $result['totals']['extra_minutes']);
        $this->assertSame(0, $result['totals']['debt_minutes']);
        $this->assertSame(
            $result['totals']['balance_minutes'],
            $result['totals']['extra_minutes'] + $result['totals']['debt_minutes']
        );
    }
}
