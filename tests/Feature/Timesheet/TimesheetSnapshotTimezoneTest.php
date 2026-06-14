<?php

namespace Tests\Feature\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetStatus;
use App\Models\Company;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Timesheet\TimesheetSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimesheetSnapshotTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_snapshot_clocked_at_matches_company_local_time_when_generated_outside_request(): void
    {
        // Simula o contexto de um worker de queue: o middleware SetCompanyTimezone
        // nunca roda, então date_default_timezone_get() permanece no default da app
        // (Europe/Madrid), enquanto a empresa está em America/Sao_Paulo (-03:00).
        $this->assertSame('Europe/Madrid', date_default_timezone_get());

        $company = Company::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $employee = User::factory()->create(['company_id' => $company->id]);
        $employee->assignRole('employee');

        TimeEntry::create([
            'company_id' => $company->id,
            'user_id' => $employee->id,
            'clocked_at' => '2026-05-24 09:00:00',
            'type' => 'in',
            'source' => 'web',
        ]);

        $closure = MonthlyClosure::create([
            'company_id' => $company->id,
            'closed_by' => $employee->id,
            'reference_year' => 2026,
            'reference_month' => 5,
            'status' => ClosureStatus::OPEN->value,
            'closed_at' => now(),
        ]);

        $timesheet = EmployeeTimesheet::create([
            'company_id' => $company->id,
            'monthly_closure_id' => $closure->id,
            'employee_id' => $employee->id,
            'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
        ]);

        app(TimesheetSnapshotService::class)->generate($timesheet);

        $timesheet->refresh();

        $day = collect($timesheet->snapshot['days'])->firstWhere('date', '2026-05-24');
        $this->assertNotNull($day, 'Dia 2026-05-24 não encontrado no snapshot.');

        $entry = collect($day['entries'])->first();
        $this->assertSame('2026-05-24T09:00-03:00', $entry['clocked_at']);
    }
}
