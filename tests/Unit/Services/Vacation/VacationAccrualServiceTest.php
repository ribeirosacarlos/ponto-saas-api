<?php

namespace Tests\Unit\Services\Vacation;

use App\Models\Absence;
use App\Models\LeavePolicy;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use App\Services\VacationAccrualService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacationAccrualServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_days_full_year_without_absences(): void
    {
        $user = User::factory()->create();
        $policy = $this->createPolicy($user, [
            'annual_entitlement_days' => 30.00,
            'accrual_basis' => 'calendar_days',
        ]);

        $service = app(VacationAccrualService::class);
        $result = $service->calculate($user, $policy, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertSame(365, $result['computable_days']);
        $this->assertSame(0, $result['non_computable_days']);
        $this->assertEqualsWithDelta(30.0, $result['accrued_days'], 0.01);
    }

    public function test_calendar_days_unpaid_leave_reduces_accrual(): void
    {
        $user = User::factory()->create();
        $policy = $this->createPolicy($user, [
            'annual_entitlement_days' => 30.00,
            'accrual_basis' => 'calendar_days',
        ]);

        $this->createAbsence($user, '2026-03-01', '2026-03-31', false);

        $service = app(VacationAccrualService::class);
        $result = $service->calculate($user, $policy, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertSame(334, $result['computable_days']);
        $this->assertSame(31, $result['non_computable_days']);
        $this->assertEqualsWithDelta(334 * (30 / 365), $result['accrued_days'], 0.01);
    }

    public function test_calendar_days_it_leave_does_not_reduce_accrual(): void
    {
        $user = User::factory()->create();
        $policy = $this->createPolicy($user, [
            'annual_entitlement_days' => 30.00,
            'accrual_basis' => 'calendar_days',
        ]);

        $this->createAbsence($user, '2026-03-01', '2026-03-31', true);

        $service = app(VacationAccrualService::class);
        $result = $service->calculate($user, $policy, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertSame(365, $result['computable_days']);
        $this->assertSame(0, $result['non_computable_days']);
        $this->assertEqualsWithDelta(30.0, $result['accrued_days'], 0.01);
    }

    public function test_scheduled_workdays_mon_fri(): void
    {
        $user = User::factory()->create();
        $this->assignMonFriShift($user, '2026-01-01', null);

        $policy = $this->createPolicy($user, [
            'annual_entitlement_days' => 22.00,
            'accrual_basis' => 'scheduled_workdays',
        ]);

        $expectedScheduled = $this->countWeekdays(2026);

        $service = app(VacationAccrualService::class);
        $result = $service->calculate($user, $policy, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertSame($expectedScheduled, $result['computable_days']);
        $this->assertEqualsWithDelta(22.0, $result['accrued_days'], 0.01);
    }

    public function test_unjustified_absence_reduces_accrual(): void
    {
        $user = User::factory()->create();
        $policy = $this->createPolicy($user, [
            'annual_entitlement_days' => 30.00,
            'accrual_basis' => 'calendar_days',
        ]);

        $this->createAbsence($user, '2026-06-10', '2026-06-10', false);

        $service = app(VacationAccrualService::class);
        $result = $service->calculate($user, $policy, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertSame(364, $result['computable_days']);
        $this->assertSame(1, $result['non_computable_days']);
        $this->assertEqualsWithDelta(364 * (30 / 365), $result['accrued_days'], 0.01);
    }

    public function test_rejected_time_entries_do_not_count_as_worked(): void
    {
        $user = User::factory()->create();
        $this->assignMonFriShift($user, '2026-01-01', null);

        $policy = $this->createPolicy($user, [
            'annual_entitlement_days' => 22.00,
            'accrual_basis' => 'scheduled_workdays',
            'day_work_threshold_minutes' => 1,
        ]);

        $this->createTimeEntry($user, '2026-01-03 09:00:00', 'in', 'rejected');
        $this->createTimeEntry($user, '2026-01-03 17:00:00', 'out', 'rejected');

        $expectedScheduled = $this->countWeekdays(2026);

        $service = app(VacationAccrualService::class);
        $result = $service->calculate($user, $policy, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertSame($expectedScheduled, $result['computable_days']);
    }

    private function createPolicy(User $user, array $overrides = []): LeavePolicy
    {
        return LeavePolicy::create([
            'company_id' => $user->company_id,
            'name' => 'Policy ' . uniqid(),
            'days_per_year' => $overrides['annual_entitlement_days'] ?? 30.00,
            'accrual_rate_per_month' => round(($overrides['annual_entitlement_days'] ?? 30.00) / 12, 3),
            'annual_entitlement_days' => $overrides['annual_entitlement_days'] ?? 30.00,
            'accrual_basis' => $overrides['accrual_basis'] ?? 'calendar_days',
            'day_work_threshold_minutes' => $overrides['day_work_threshold_minutes'] ?? 1,
            'counting_method' => 'calendar_days',
            'allow_carry_over' => false,
        ]);
    }

    private function createAbsence(User $user, string $start, string $end, bool $countsForAccrual): Absence
    {
        return Absence::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'type' => 'TEST',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'recorded',
            'comment' => 'Test absence',
            'counts_for_accrual' => $countsForAccrual,
            'created_by' => $user->id,
        ]);
    }

    private function assignMonFriShift(User $user, string $startDate, ?string $endDate): UserShift
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => 'Mon-Fri',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'is_default' => false,
            'is_flexible' => false,
        ]);

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            ShiftDay::create([
                'shift_id' => $shift->id,
                'weekday' => $weekday,
                'is_working_day' => true,
                'start_time' => '09:00:00',
                'end_time' => '18:00:00',
            ]);
        }

        return UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    private function createTimeEntry(User $user, string $clockedAt, string $type, ?string $adjustmentStatus): TimeEntry
    {
        return TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => Carbon::parse($clockedAt, 'Europe/Madrid'),
            'type' => $type,
            'source' => 'web',
            'adjustment_status' => $adjustmentStatus,
        ]);
    }

    private function countWeekdays(int $year): int
    {
        $start = Carbon::create($year, 1, 1);
        $end = Carbon::create($year, 12, 31);
        $count = 0;

        foreach (CarbonPeriod::create($start, $end) as $date) {
            if (in_array((int) $date->isoWeekday(), [1, 2, 3, 4, 5], true)) {
                $count++;
            }
        }

        return $count;
    }
}
