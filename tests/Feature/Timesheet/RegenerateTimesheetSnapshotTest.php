<?php

namespace Tests\Feature\Timesheet;

use App\Enums\ClosureStatus;
use App\Enums\TimesheetSignatureRole;
use App\Enums\TimesheetStatus;
use App\Models\Company;
use App\Models\EmployeeTimesheet;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\TimeEntry;
use App\Models\TimesheetSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegenerateTimesheetSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
    }

    public function test_regenerates_stale_snapshot_for_unsigned_timesheet(): void
    {
        $timesheet = $this->createTimesheetWithStaleSnapshot();

        $this->artisan('timesheets:regenerate-snapshot', ['timesheet' => $timesheet->id])
            ->assertExitCode(0);

        $this->assertSame('2026-05-24T09:00-03:00', $this->firstEntryClockedAt($timesheet->refresh()));
    }

    public function test_closure_option_regenerates_all_timesheets_of_closure(): void
    {
        $timesheet = $this->createTimesheetWithStaleSnapshot();

        $this->artisan('timesheets:regenerate-snapshot', ['--closure' => $timesheet->monthly_closure_id])
            ->assertExitCode(0);

        $this->assertSame('2026-05-24T09:00-03:00', $this->firstEntryClockedAt($timesheet->refresh()));
    }

    public function test_skips_timesheet_with_active_signature_unless_forced(): void
    {
        $timesheet = $this->createTimesheetWithStaleSnapshot();
        $this->createActiveSignature($timesheet);

        $this->artisan('timesheets:regenerate-snapshot', ['timesheet' => $timesheet->id])
            ->assertExitCode(0);

        $timesheet->refresh();
        $this->assertSame('2026-05-24T04:00-03:00', $this->firstEntryClockedAt($timesheet));
        $this->assertEquals(
            1,
            TimesheetSignature::where('employee_timesheet_id', $timesheet->id)->whereNull('superseded_at')->count()
        );

        $this->artisan('timesheets:regenerate-snapshot', ['timesheet' => $timesheet->id, '--force' => true])
            ->assertExitCode(0);

        $timesheet->refresh();
        $this->assertSame('2026-05-24T09:00-03:00', $this->firstEntryClockedAt($timesheet));
        $this->assertEquals(TimesheetStatus::PENDING_EMPLOYEE, $timesheet->status);
        $this->assertEquals(
            0,
            TimesheetSignature::where('employee_timesheet_id', $timesheet->id)->whereNull('superseded_at')->count()
        );
    }

    public function test_scan_regenerates_only_timesheets_without_active_signatures(): void
    {
        $unsigned = $this->createTimesheetWithStaleSnapshot();
        $signed = $this->createTimesheetWithStaleSnapshot();
        $this->createActiveSignature($signed);

        $this->artisan('timesheets:regenerate-snapshot', ['--scan' => true])
            ->assertExitCode(0);

        $this->assertSame('2026-05-24T09:00-03:00', $this->firstEntryClockedAt($unsigned->refresh()));
        $this->assertSame('2026-05-24T04:00-03:00', $this->firstEntryClockedAt($signed->refresh()));
    }

    public function test_scan_handles_timesheets_from_different_companies(): void
    {
        $first = $this->createTimesheetWithStaleSnapshot();
        $second = $this->createTimesheetWithStaleSnapshot();

        $this->assertNotEquals($first->company_id, $second->company_id);

        $this->artisan('timesheets:regenerate-snapshot', ['--scan' => true])
            ->assertExitCode(0);

        $this->assertSame('2026-05-24T09:00-03:00', $this->firstEntryClockedAt($first->refresh()));
        $this->assertSame('2026-05-24T09:00-03:00', $this->firstEntryClockedAt($second->refresh()));
    }

    private function createTimesheetWithStaleSnapshot(): EmployeeTimesheet
    {
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

        return EmployeeTimesheet::create([
            'company_id' => $company->id,
            'monthly_closure_id' => $closure->id,
            'employee_id' => $employee->id,
            'status' => TimesheetStatus::PENDING_EMPLOYEE->value,
            'snapshot_generated_at' => now(),
            // Simula um snapshot gerado antes da correção de timezone (bb1b84e):
            // clocked_at "09:00" foi salvo como "04:00-03:00".
            'snapshot' => [
                'totals' => [],
                'days' => [
                    [
                        'date' => '2026-05-24',
                        'entries' => [
                            ['clocked_at' => '2026-05-24T04:00-03:00'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function createActiveSignature(EmployeeTimesheet $timesheet): TimesheetSignature
    {
        return TimesheetSignature::create([
            'company_id' => $timesheet->company_id,
            'employee_timesheet_id' => $timesheet->id,
            'signer_id' => $timesheet->employee_id,
            'role' => TimesheetSignatureRole::EMPLOYEE->value,
            'signed_at' => now(),
        ]);
    }

    private function firstEntryClockedAt(EmployeeTimesheet $timesheet): string
    {
        $day = collect($timesheet->snapshot['days'])->firstWhere('date', '2026-05-24');

        return $day['entries'][0]['clocked_at'];
    }
}
