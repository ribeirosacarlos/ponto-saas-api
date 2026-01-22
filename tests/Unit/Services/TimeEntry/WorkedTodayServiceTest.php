<?php

namespace Tests\Unit\Services\TimeEntry;

use App\Models\Company;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeEntry\WorkedTodayService;
use App\Services\UserShiftResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkedTodayServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_worked_seconds_matches_pair_sum_without_auto_deductions()
    {
        config(['app.timezone' => 'UTC']);

        $company = Company::factory()->create(['timezone' => 'UTC']);
        $user = User::factory()->create(['company_id' => $company->id]);

        $entries = [
            ['type' => 'in', 'clocked_at' => '2026-01-22 08:00:00'],
            ['type' => 'out', 'clocked_at' => '2026-01-22 12:00:00'],
            ['type' => 'in', 'clocked_at' => '2026-01-22 13:00:00'],
            ['type' => 'out', 'clocked_at' => '2026-01-22 16:58:40'],
        ];

        foreach ($entries as $entry) {
            TimeEntry::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'type' => $entry['type'],
                'clocked_at' => CarbonImmutable::parse($entry['clocked_at'], 'UTC'),
            ]);
        }

        $now = CarbonImmutable::parse('2026-01-22 17:00:00', 'UTC');

        $service = new WorkedTodayService(new UserShiftResolver());
        $result = $service->getWorkedToday($user, $now);

        $this->assertSame(28720, $result['worked_seconds']);
        $this->assertSame(478, $result['worked_minutes']);
        $this->assertSame(7.98, $result['worked_hours_decimal']);
        $this->assertSame(0, $result['break_seconds_deducted']);
        $this->assertSame(0, $result['expected_break_minutes']);
    }
}
