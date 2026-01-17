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

    private function assignShift(User $user, int $weekday, array $options = []): Shift
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => $options['name'] ?? 'Shift ' . $weekday,
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
        $this->assertEquals(60, $result['totals']['extra_minutes']);
        $this->assertEquals('+01:00', $result['totals']['balance_hhmm']);
        $this->assertEquals('extra', $result['days'][0]['status']);
        $this->assertFalse($result['days'][0]['ignored']);
        $this->assertEquals(60, $result['days'][0]['balance_minutes']);
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

        $this->assertEquals(-240, $result['totals']['debt_minutes']);
        $this->assertEquals(240, $result['totals']['abs_debt_minutes']);
        $this->assertEquals('debt', $result['days'][0]['status']);
        $this->assertEquals('-04:00', $result['days'][0]['balance_hhmm']);
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
}
