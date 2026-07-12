<?php

namespace Tests\Feature;

use App\Enums\ClosureStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Absence;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use App\Services\TimeEntry\AbsenceTimeEntryService;
use App\Services\TimeEntry\OvertimeCalculatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceAllowanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(EnsureCompanyHasAccess::class);

        Role::updateOrCreate(['name' => 'employee'], ['display_name' => 'Employee']);
        Role::updateOrCreate(['name' => 'admin'], ['display_name' => 'Admin']);
        Role::updateOrCreate(['name' => 'manager'], ['display_name' => 'Manager']);
        Role::updateOrCreate(['name' => 'area_manager'], ['display_name' => 'Area Manager']);
    }

    public function test_admin_creates_full_day_allowance_that_does_not_generate_overtime_debt(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $response = $this->actingAs($admin)->postJson('/v1/admin/absences', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
            'comment' => 'Abono administrativo',
        ]);

        $response->assertCreated()
            ->assertJsonPath('type', Absence::TYPE_EXCUSED_ABSENCE)
            ->assertJsonPath('status', Absence::STATUS_RECORDED)
            ->assertJsonPath('coverage_type', Absence::COVERAGE_FULL_DAY)
            ->assertJsonPath('warnings', []);

        $result = $this->calculateOvertime($employee, '2026-04-10');

        $this->assertSame(540, $result['days'][0]['summary']['expected_minutes']);
        $this->assertSame(540, $result['days'][0]['summary']['worked_minutes']);
        $this->assertSame(0, $result['days'][0]['summary']['raw_worked_minutes']);
        $this->assertSame(0, $result['days'][0]['summary']['debt_minutes']);
        $this->assertSame(0, $result['totals']['debt_minutes']);
        $this->assertTrue($result['days'][0]['summary']['is_absence']);
        $this->assertSame(Absence::TYPE_EXCUSED_ABSENCE, $result['days'][0]['summary']['absence_type']);
        // Turno 08:00-17:00 com break 12:00-13:00: um abono de dia inteiro cruza o intervalo,
        // entao devem ser gerados 4 eventos (work_start/break_start/break_end/work_end), nao 2.
        $this->assertDatabaseCount('time_entries', 4);
        $this->assertDatabaseHas('time_entries', [
            'absence_id' => $response->json('id'),
            'source' => 'absence_allowance',
            'device_type' => 'system',
            'type' => 'in',
            'event_kind' => 'work_start',
        ]);
        $this->assertDatabaseHas('time_entries', [
            'absence_id' => $response->json('id'),
            'source' => 'absence_allowance',
            'device_type' => 'system',
            'type' => 'out',
            'event_kind' => 'break_start',
        ]);
        $this->assertDatabaseHas('time_entries', [
            'absence_id' => $response->json('id'),
            'source' => 'absence_allowance',
            'device_type' => 'system',
            'type' => 'in',
            'event_kind' => 'break_end',
        ]);
        $this->assertDatabaseHas('time_entries', [
            'absence_id' => $response->json('id'),
            'source' => 'absence_allowance',
            'device_type' => 'system',
            'type' => 'out',
            'event_kind' => 'work_end',
        ]);
    }

    public function test_admin_creates_hourly_allowance_that_reduces_expected_minutes(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $response = $this->actingAs($admin)->postJson('/v1/admin/absences', [
            'user_id' => $employee->id,
            'type' => 'personal_reason',
            'coverage_type' => Absence::COVERAGE_HOURS,
            'date' => '2026-04-10',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'comment' => 'Abono por motivo pessoal',
        ]);

        $response->assertCreated()
            ->assertJsonPath('type', 'personal_reason')
            ->assertJsonPath('coverage_type', Absence::COVERAGE_HOURS);

        // Abono nao cobre a jornada inteira (120 de 540), entao deve vir com aviso de cobertura incompleta.
        $warnings = $response->json('warnings');
        $this->assertCount(1, $warnings);
        $this->assertSame('2026-04-10', $warnings[0]['date']);
        $this->assertSame(420, $warnings[0]['missing_minutes']);

        $result = $this->calculateOvertime($employee, '2026-04-10');

        $this->assertSame(540, $result['days'][0]['summary']['expected_minutes']);
        $this->assertSame(120, $result['days'][0]['summary']['worked_minutes']);
        $this->assertSame(120, $result['days'][0]['summary']['absence_minutes']);
        $this->assertSame(Absence::COVERAGE_HOURS, $result['days'][0]['summary']['absence_coverage_type']);
        $this->assertDatabaseCount('time_entries', 2);
    }

    public function test_admin_creates_hourly_allowance_crossing_shift_break_excludes_break_minutes(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $response = $this->actingAs($admin)->postJson('/v1/admin/absences', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_HOURS,
            'date' => '2026-04-10',
            'start_time' => '11:00',
            'end_time' => '14:00',
            'comment' => 'Abono cruzando o intervalo',
        ]);

        $response->assertCreated();
        $absenceId = $response->json('id');

        // 11:00-14:00 cruza o break 12:00-13:00 do turno: devem sobrar 2 pares (11:00-12:00 e 13:00-14:00),
        // ou seja, 4 TimeEntry no total, e o intervalo nao deve contar como minuto abonado.
        $this->assertDatabaseCount('time_entries', 4);
        $this->assertDatabaseHas('time_entries', [
            'absence_id' => $absenceId,
            'type' => 'in',
            'event_kind' => 'work_start',
        ]);
        $this->assertDatabaseHas('time_entries', [
            'absence_id' => $absenceId,
            'type' => 'out',
            'event_kind' => 'break_start',
        ]);
        $this->assertDatabaseHas('time_entries', [
            'absence_id' => $absenceId,
            'type' => 'in',
            'event_kind' => 'break_end',
        ]);
        $this->assertDatabaseHas('time_entries', [
            'absence_id' => $absenceId,
            'type' => 'out',
            'event_kind' => 'work_end',
        ]);

        $result = $this->calculateOvertime($employee, '2026-04-10');

        // 3h pedidas (180 min) menos 60 min de intervalo = 120 min abonados de verdade.
        $this->assertSame(120, $result['days'][0]['summary']['absence_minutes']);
        $this->assertSame(120, $result['days'][0]['summary']['worked_minutes']);

        $warnings = $response->json('warnings');
        $this->assertCount(1, $warnings);
        $this->assertSame(420, $warnings[0]['missing_minutes']);
    }

    public function test_admin_creates_hourly_allowance_outside_shift_window_is_not_clipped(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        // Turno e 08:00-17:00, mas o abono comeca as 06:00 (2h antes do turno abrir).
        // O periodo abonado deve ser respeitado integralmente, sem recorte pelo horario do shift.
        $response = $this->actingAs($admin)->postJson('/v1/admin/absences', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_HOURS,
            'date' => '2026-04-10',
            'start_time' => '06:00',
            'end_time' => '08:00',
            'comment' => 'Consulta medica antes do turno',
        ]);

        $response->assertCreated();
        $absenceId = $response->json('id');

        $this->assertDatabaseCount('time_entries', 2);
        $this->assertDatabaseHas('time_entries', [
            'absence_id' => $absenceId,
            'clocked_at' => '2026-04-10 06:00:00',
            'type' => 'in',
            'event_kind' => 'work_start',
        ]);
        $this->assertDatabaseHas('time_entries', [
            'absence_id' => $absenceId,
            'clocked_at' => '2026-04-10 08:00:00',
            'type' => 'out',
            'event_kind' => 'work_end',
        ]);

        $result = $this->calculateOvertime($employee, '2026-04-10');

        $this->assertSame(120, $result['days'][0]['summary']['absence_minutes']);
        $this->assertSame(120, $result['days'][0]['summary']['worked_minutes']);
    }

    public function test_absence_time_entry_generation_is_idempotent(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $response = $this->actingAs($admin)->postJson('/v1/admin/absences', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ]);

        $response->assertCreated();
        $absence = Absence::query()->findOrFail($response->json('id'));

        app(AbsenceTimeEntryService::class)->syncForAbsence($absence);
        app(AbsenceTimeEntryService::class)->syncForAbsence($absence);

        $this->assertSame(4, TimeEntry::query()
            ->where('absence_id', $absence->id)
            ->where('source', 'absence_allowance')
            ->count());

        $result = $this->calculateOvertime($employee, '2026-04-10');

        $this->assertSame(540, $result['days'][0]['summary']['worked_minutes']);
        $this->assertSame(0, $result['days'][0]['summary']['raw_worked_minutes']);
        $this->assertSame(0, $result['totals']['extra_minutes']);
        $this->assertSame(0, $result['totals']['debt_minutes']);
    }

    public function test_admin_cannot_create_allowance_in_monthly_closure_period(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);

        MonthlyClosure::create([
            'company_id' => $admin->company_id,
            'closed_by' => $admin->id,
            'employee_id' => $employee->id,
            'reference_year' => 2026,
            'reference_month' => 4,
            'status' => ClosureStatus::OPEN->value,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)->postJson('/v1/admin/absences', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('start_date');
    }

    public function test_other_employee_monthly_closure_does_not_block_allowance(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $otherEmployee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        MonthlyClosure::create([
            'company_id' => $admin->company_id,
            'closed_by' => $admin->id,
            'employee_id' => $otherEmployee->id,
            'reference_year' => 2026,
            'reference_month' => 4,
            'status' => ClosureStatus::OPEN->value,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)->postJson('/v1/admin/absences', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ])->assertCreated();

        $this->assertDatabaseCount('time_entries', 4);
    }

    public function test_admin_deletes_allowance_and_generated_time_entries(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $response = $this->actingAs($admin)->postJson('/v1/admin/absences', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ]);

        $response->assertCreated();
        $absenceId = $response->json('id');

        $this->assertDatabaseHas('absences', ['id' => $absenceId]);
        $this->assertSame(4, TimeEntry::query()->where('absence_id', $absenceId)->count());

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/absences/{$absenceId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('absences', ['id' => $absenceId]);
        $this->assertSame(0, TimeEntry::withTrashed()->where('absence_id', $absenceId)->count());
    }

    public function test_admin_cannot_delete_allowance_in_monthly_closure_period(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $response = $this->actingAs($admin)->postJson('/v1/admin/absences', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ]);

        $response->assertCreated();
        $absenceId = $response->json('id');

        MonthlyClosure::create([
            'company_id' => $admin->company_id,
            'closed_by' => $admin->id,
            'employee_id' => $employee->id,
            'reference_year' => 2026,
            'reference_month' => 4,
            'status' => ClosureStatus::OPEN->value,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/absences/{$absenceId}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_date');

        $this->assertDatabaseHas('absences', ['id' => $absenceId]);
        $this->assertSame(4, TimeEntry::query()->where('absence_id', $absenceId)->count());
    }

    public function test_other_employee_monthly_closure_does_not_block_allowance_delete(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $otherEmployee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $response = $this->actingAs($admin)->postJson('/v1/admin/absences', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ]);

        $response->assertCreated();
        $absenceId = $response->json('id');

        MonthlyClosure::create([
            'company_id' => $admin->company_id,
            'closed_by' => $admin->id,
            'employee_id' => $otherEmployee->id,
            'reference_year' => 2026,
            'reference_month' => 4,
            'status' => ClosureStatus::OPEN->value,
            'closed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/admin/absences/{$absenceId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('absences', ['id' => $absenceId]);
        $this->assertSame(0, TimeEntry::withTrashed()->where('absence_id', $absenceId)->count());
    }

    public function test_admin_cannot_delete_allowance_from_another_company(): void
    {
        $admin = $this->createAdmin();
        $otherAdmin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $response = $this->actingAs($admin)->postJson('/v1/admin/absences', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ]);

        $response->assertCreated();
        $absenceId = $response->json('id');

        $this->actingAs($otherAdmin)
            ->deleteJson("/api/v1/admin/absences/{$absenceId}")
            ->assertNotFound();

        $this->assertDatabaseHas('absences', ['id' => $absenceId]);
        $this->assertSame(4, TimeEntry::query()->where('absence_id', $absenceId)->count());
    }

    private function createAdmin(?string $companyId = null): User
    {
        $admin = User::factory()->create($companyId ? ['company_id' => $companyId] : []);
        $admin->syncRoles(['admin']);

        return $admin;
    }

    private function createEmployee(?string $companyId = null): User
    {
        $employee = User::factory()->create($companyId ? ['company_id' => $companyId] : []);
        $employee->syncRoles(['employee']);

        return $employee;
    }

    private function assignShift(User $employee, string $date): void
    {
        $date = CarbonImmutable::parse($date, 'UTC');
        $shift = Shift::create([
            'company_id' => $employee->company_id,
            'name' => 'Jornada padrao',
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
            'break_start_time' => '12:00',
            'break_end_time' => '13:00',
            'break_minutes' => 60,
            'scheduled_minutes' => 540,
        ]);

        UserShift::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'shift_id' => $shift->id,
            'start_date' => '2026-04-01',
        ]);
    }

    private function calculateOvertime(User $employee, string $date): array
    {
        return app(OvertimeCalculatorService::class)->calculateForEmployee(
            $employee,
            CarbonImmutable::parse($date, 'UTC'),
            CarbonImmutable::parse($date, 'UTC'),
            true,
        );
    }
}
