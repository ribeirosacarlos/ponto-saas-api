<?php

namespace Tests\Feature;

use App\Enums\ClosureStatus;
use App\Http\Middleware\EnsureCompanyHasAccess;
use App\Models\Absence;
use App\Models\Area;
use App\Models\Company;
use App\Models\Document;
use App\Models\MonthlyClosure;
use App\Models\Role;
use App\Models\Shift;
use App\Models\ShiftDay;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserShift;
use App\Models\VacationDay;
use App\Models\VacationRequest;
use App\Services\TimeEntry\OvertimeCalculatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MedicalCertificateTest extends TestCase
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

    public function test_employee_creates_pending_medical_certificate_without_attachment(): void
    {
        $employee = $this->createEmployee();
        $this->assignShift($employee, '2026-04-10');

        $response = $this->actingAs($employee)->postJson('/v1/employee/medical-certificates', [
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'comment' => 'Atestado enviado pelo colaborador',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', Absence::STATUS_PENDING)
            ->assertJsonPath('data.coverage_type', Absence::COVERAGE_FULL_DAY);

        $this->assertDatabaseHas('absences', [
            'user_id' => $employee->id,
            'type' => Absence::TYPE_SICK_LEAVE,
            'status' => Absence::STATUS_PENDING,
            'counts_for_accrual' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $employee->company_id,
            'action' => 'medical_certificate.created',
        ]);

        $this->assertDatabaseCount('time_entries', 0);
    }

    public function test_employee_creates_medical_certificate_with_attachment(): void
    {
        Storage::fake('s3');
        $employee = $this->createEmployee();
        $file = UploadedFile::fake()->createWithContent('atestado.pdf', "%PDF-1.4\n%test\n");

        $response = $this->actingAs($employee)->postJson('/v1/employee/medical-certificates', [
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
            'comment' => 'Com anexo',
            'files' => [$file],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.documents.0.original_name', 'atestado.pdf');

        $document = Document::query()->firstOrFail();
        $absence = Absence::query()->firstOrFail();

        Storage::disk('s3')->assertExists($document->path);
        $this->assertTrue($absence->documents()->whereKey($document->id)->exists());
    }

    public function test_admin_creates_approved_medical_certificate_for_visible_employee(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $response = $this->actingAs($admin)->postJson('/v1/admin/medical-certificates', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
            'comment' => 'Lançado pelo admin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', Absence::STATUS_APPROVED)
            ->assertJsonPath('data.approved_by', (string) $admin->id);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $admin->company_id,
            'action' => 'medical_certificate.created_approved',
        ]);

        $this->assertDatabaseCount('time_entries', 2);
        $this->assertDatabaseHas('time_entries', [
            'absence_id' => $response->json('data.id'),
            'source' => 'absence_allowance',
            'device_type' => 'system',
            'type' => 'in',
        ]);
    }

    public function test_area_manager_cannot_create_for_employee_outside_visibility(): void
    {
        $company = Company::factory()->create();
        $managedArea = Area::factory()->create(['company_id' => $company->id]);
        $otherArea = Area::factory()->create(['company_id' => $company->id]);

        $manager = User::factory()->create(['company_id' => $company->id]);
        $manager->syncRoles(['area_manager']);
        $manager->managedAreas()->attach($managedArea->id, [
            'id' => (string) Str::uuid(),
            'company_id' => $company->id,
        ]);

        $employee = $this->createEmployee($company->id, ['area_id' => $otherArea->id]);

        $this->actingAs($manager)->postJson('/v1/admin/medical-certificates', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ])->assertNotFound();
    }

    public function test_approved_full_day_medical_certificate_fills_expected_minutes(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $this->actingAs($admin)->postJson('/v1/admin/medical-certificates', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ])->assertCreated();

        $result = $this->calculateOvertime($employee, '2026-04-10');

        $this->assertSame(540, $result['days'][0]['summary']['expected_minutes']);
        $this->assertSame(540, $result['days'][0]['summary']['worked_minutes']);
        $this->assertSame(0, $result['days'][0]['summary']['raw_worked_minutes']);
        $this->assertTrue($result['days'][0]['summary']['is_absence']);
        $this->assertSame(Absence::COVERAGE_FULL_DAY, $result['days'][0]['summary']['absence_coverage_type']);
    }

    public function test_approved_hourly_medical_certificate_adds_absence_minutes_partially(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $this->actingAs($admin)->postJson('/v1/admin/medical-certificates', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_HOURS,
            'date' => '2026-04-10',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ])->assertCreated();

        $result = $this->calculateOvertime($employee, '2026-04-10');

        $this->assertSame(540, $result['days'][0]['summary']['expected_minutes']);
        $this->assertSame(120, $result['days'][0]['summary']['worked_minutes']);
        $this->assertSame(120, $result['days'][0]['summary']['absence_minutes']);
        $this->assertSame(Absence::COVERAGE_HOURS, $result['days'][0]['summary']['absence_coverage_type']);
        $this->assertDatabaseCount('time_entries', 2);
    }

    public function test_existing_time_entries_are_kept_and_counted_on_approved_certificate_day(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $this->createTimeEntry($employee, 'in', '2026-04-10 08:00:00');
        $this->createTimeEntry($employee, 'out', '2026-04-10 12:00:00');

        $this->actingAs($admin)->postJson('/v1/admin/medical-certificates', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ])->assertCreated();

        $result = $this->calculateOvertime($employee, '2026-04-10');

        $this->assertSame(540, $result['days'][0]['summary']['expected_minutes']);
        $this->assertSame(240, $result['days'][0]['summary']['raw_worked_minutes']);
        $this->assertSame(540, $result['days'][0]['summary']['worked_minutes']);

        $this->assertDatabaseCount('time_entries', 4);
        $this->assertSame(2, TimeEntry::query()->where('source', 'absence_allowance')->count());
    }

    public function test_overlap_with_existing_absence_returns_validation_error(): void
    {
        $employee = $this->createEmployee();

        Absence::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'type' => Absence::TYPE_SICK_LEAVE,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-10',
            'status' => Absence::STATUS_PENDING,
            'counts_for_accrual' => true,
            'created_by' => $employee->id,
        ]);

        $this->actingAs($employee)->postJson('/v1/employee/medical-certificates', [
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('start_date');
    }

    public function test_overlap_with_vacation_returns_validation_error(): void
    {
        $employee = $this->createEmployee();

        $request = VacationRequest::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-10',
            'requested_days' => 1,
            'status' => 'approved',
            'requested_by' => $employee->id,
            'approved_by' => $employee->id,
            'approved_at' => now(),
        ]);

        VacationDay::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'vacation_request_id' => $request->id,
            'date' => '2026-04-10',
        ]);

        $this->actingAs($employee)->postJson('/v1/employee/medical-certificates', [
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('start_date');
    }

    public function test_period_in_monthly_closure_returns_validation_error(): void
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

        $this->actingAs($admin)->postJson('/v1/admin/medical-certificates', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('start_date');
    }

    public function test_other_employee_monthly_closure_does_not_block_medical_certificate(): void
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

        $this->actingAs($admin)->postJson('/v1/admin/medical-certificates', [
            'user_id' => $employee->id,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
        ])->assertCreated();

        $this->assertDatabaseCount('time_entries', 2);
    }

    public function test_rejected_and_canceled_certificates_do_not_affect_calculation(): void
    {
        $admin = $this->createAdmin();
        $employee = $this->createEmployee($admin->company_id);
        $this->assignShift($employee, '2026-04-10');

        $pending = Absence::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'type' => Absence::TYPE_SICK_LEAVE,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-10',
            'status' => Absence::STATUS_PENDING,
            'counts_for_accrual' => true,
            'created_by' => $employee->id,
        ]);

        $this->actingAs($admin)->patchJson("/v1/admin/medical-certificates/{$pending->id}/reject", [
            'rejection_reason' => 'Documento inválido',
        ])->assertOk();

        $canceled = Absence::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'type' => Absence::TYPE_SICK_LEAVE,
            'coverage_type' => Absence::COVERAGE_FULL_DAY,
            'start_date' => '2026-04-11',
            'end_date' => '2026-04-11',
            'status' => Absence::STATUS_CANCELED,
            'counts_for_accrual' => true,
            'created_by' => $employee->id,
            'canceled_by' => $employee->id,
            'canceled_at' => now(),
        ]);

        $this->assertNotNull($canceled->id);

        $result = $this->calculateOvertime($employee, '2026-04-10');

        $this->assertSame(540, $result['days'][0]['summary']['expected_minutes']);
        $this->assertFalse($result['days'][0]['summary']['is_absence']);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $employee->company_id,
            'action' => 'medical_certificate.rejected',
        ]);
    }

    private function createAdmin(?string $companyId = null): User
    {
        $admin = User::factory()->create($companyId ? ['company_id' => $companyId] : []);
        $admin->syncRoles(['admin']);

        return $admin;
    }

    private function createEmployee(?string $companyId = null, array $overrides = []): User
    {
        $employee = User::factory()->create(array_merge(
            $companyId ? ['company_id' => $companyId] : [],
            $overrides,
        ));
        $employee->syncRoles(['employee']);

        return $employee;
    }

    private function assignShift(User $employee, string $date): void
    {
        $date = CarbonImmutable::parse($date, 'UTC');
        $shift = Shift::create([
            'company_id' => $employee->company_id,
            'name' => 'Jornada padrão',
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

    private function createTimeEntry(User $employee, string $type, string $clockedAt): void
    {
        TimeEntry::create([
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'clocked_at' => $clockedAt,
            'type' => $type,
            'source' => 'web',
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
