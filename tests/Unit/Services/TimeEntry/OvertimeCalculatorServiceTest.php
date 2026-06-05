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

    public function test_odd_number_of_entries_is_ignored(): void
    {
        $date = CarbonImmutable::parse('2025-12-16', 'UTC');
        $user = User::factory()->create();
        $this->assignShift($user, $date->isoWeekday());

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(12, 0));
        $this->createTimeEntry($user, 'in', $date->setTime(13, 0));

        $service = app(OvertimeCalculatorService::class);
        $result = $service->calculateForEmployee($user, $date, $date, true);

        $this->assertEquals(0, $result['totals']['worked_minutes']);
        $this->assertTrue($result['days'][0]['ignored']);
        $this->assertSame('open_day_odd_entries', $result['days'][0]['reason']);
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

        $this->createTimeEntry($user, 'in', CarbonImmutable::parse('2025-12-19 00:30:00', 'UTC'));
        $this->createTimeEntry($user, 'out', CarbonImmutable::parse('2025-12-19 02:30:00', 'UTC'));

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
        $this->assertSame(-10, $result['days'][0]['balance_minutes']);
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
        $this->assertSame(-20, $result['days'][0]['balance_minutes']);
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
        $this->assertSame(-136, $result['days'][0]['balance_minutes']);
        $this->assertSame('-02:16', $result['days'][0]['balance_hhmm']);
        $this->assertSame(0, $result['days'][0]['extra_minutes']);
        $this->assertSame('00:00', $result['days'][0]['extra_hhmm']);
        $this->assertSame(-136, $result['days'][0]['debt_minutes']);
        $this->assertSame('02:16', $result['days'][0]['debt_hhmm']);
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

        $this->assertSame(335, $result['days'][0]['worked_minutes']);
        $this->assertSame(5, $result['days'][0]['exceeded_break_minutes']);
        $this->assertSame(0, $result['days'][0]['balance_minutes']);
        $this->assertSame(0, $result['days'][0]['extra_minutes']);
        $this->assertSame(0, $result['days'][0]['debt_minutes']);
        $this->assertSame('even', $result['days'][0]['status']);
        $this->assertFalse($result['days'][0]['is_finalized']);
    }
}
