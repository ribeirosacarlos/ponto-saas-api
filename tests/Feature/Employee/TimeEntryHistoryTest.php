<?php

namespace Tests\Feature\Employee;

use App\Enums\SubscriptionStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeEntryHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_history_returns_canonical_summary_and_flat_aliases(): void
    {
        $user = User::factory()->create();
        $user->assignRole('employee');
        $user->company()->update(['subscription_status' => SubscriptionStatus::ACTIVE->value]);

        $date = CarbonImmutable::parse('2025-12-19', 'UTC');
        $this->assignShift($user, $date);

        $this->createTimeEntry($user, 'in', $date->setTime(8, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(12, 0));
        $this->createTimeEntry($user, 'in', $date->setTime(13, 0));
        $this->createTimeEntry($user, 'out', $date->setTime(17, 0));

        $response = $this->actingAs($user)
            ->getJson('/v1/employee/entries/history?from=2025-12-19&to=2025-12-19');

        $response->assertOk()
            ->assertJsonPath('days.0.date', '2025-12-19')
            ->assertJsonPath('days.0.worked_minutes', 540)
            ->assertJsonPath('days.0.worked_hhmm', '09:00')
            ->assertJsonPath('days.0.summary.worked_minutes', 540)
            ->assertJsonPath('days.0.summary.worked_hhmm', '09:00')
            ->assertJsonPath('days.0.summary.expected_minutes', 540)
            ->assertJsonPath('days.0.summary.status', 'even');
    }

    private function assignShift(User $user, CarbonImmutable $date): void
    {
        $shift = Shift::create([
            'company_id' => $user->company_id,
            'name' => 'History Shift',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'is_flexible' => false,
            'is_default' => true,
        ]);

        ShiftDay::create([
            'shift_id' => $shift->id,
            'weekday' => $date->isoWeekday(),
            'is_working_day' => true,
            'start_time' => '08:00',
            'end_time' => '17:00',
            'scheduled_minutes' => 540,
            'break_minutes' => 60,
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
        ]);

        UserShift::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'shift_id' => $shift->id,
            'start_date' => $date->startOfMonth()->toDateString(),
        ]);
    }

    private function createTimeEntry(User $user, string $type, CarbonImmutable $clockedAt): void
    {
        TimeEntry::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'clocked_at' => $clockedAt,
            'type' => $type,
            'source' => 'web',
        ]);
    }
}
